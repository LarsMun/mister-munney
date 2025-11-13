# OPDRACHT: Implementeer Security Fix voor Account Ownership in Mister Munney

## PROJECT CONTEXT

**Applicatie:** Mister Munney - Personal Finance Application
**Stack:**
- Backend: Symfony 7.2 (PHP 8.3)
- Frontend: React 19
- Database: MySQL/MariaDB
- Auth: JWT (LexikJWTAuthenticationBundle)
- ORM: Doctrine

**Project Structuur:**
```
/
├── src/
│   ├── Entity/
│   ├── Repository/
│   ├── Service/
│   ├── Controller/Api/
│   └── Exception/
├── migrations/
├── tests/
└── frontend/ (React applicatie)
```

## SECURITY PROBLEEM

**Vulnerability:** Automatic Account Ownership via CSV Import

**Huidige situatie:**
In `src/Service/AccountService.php` bestaat de methode `getOrCreateAccountByNumberForUser()` die bij CSV import van transacties automatisch een gebruiker toevoegt als eigenaar van een bankrekening als het rekeningnummer al bestaat:
```php
public function getOrCreateAccountByNumberForUser(string $accountNumber, $user): Account
{
    $account = $this->accountRepository->findByAccountNumber($accountNumber);
    if (!$account) {
        $account = new Account();
        $account->setAccountNumber($accountNumber);
        $account->addUser($user);
        $this->accountRepository->save($account);
    } elseif (!$account->isOwnedBy($user)) {
        // ⚠️ SECURITY ISSUE: Automatically adds user as owner!
        $account->addUser($user);
        $this->accountRepository->save($account);
    }
    return $account;
}
```

**Attack Scenario:**
1. Gebruiker A uploadt CSV met rekeningnummer NL01INGB1234567890
2. Kwaadwillende gebruiker B maakt CSV met HETZELFDE rekeningnummer
3. B uploadt CSV → wordt automatisch mede-eigenaar
4. B kan nu alle transacties van A zien en bewerken

## GEWENSTE OPLOSSING

### Use Case Requirements
- Partners moeten een gezamenlijke rekening BEWUST kunnen delen
- Beide moeten transacties kunnen uploaden voor gedeelde rekening
- Privacy van persoonlijke rekeningen moet gewaarborgd zijn
- Geen automatische toegang tot accounts van anderen

### Security Principes
1. **Eerste gebruiker = eigenaar**: Wie eerst een rekeningnummer uploadt wordt eigenaar
2. **Expliciet delen**: Accounts kunnen alleen gedeeld worden via uitnodiging
3. **Toegang blokkeren**: CSV imports met andermans rekeningen worden geweigerd
4. **Audit trail**: Bijhouden wie wanneer door wie uitgenodigd is

## IMPLEMENTATIE SPECIFICATIES

### 1. Database Schema Wijzigingen

**Nieuwe Many-to-Many Join Table met Extra Attributen:**

Vervang de huidige `account_users` many-to-many table door een nieuwe `account_user` entity met:

| Kolom | Type | Beschrijving |
|-------|------|--------------|
| id | INT (PK) | Primary key |
| account_id | INT (FK) | Foreign key naar account |
| user_id | INT (FK) | Foreign key naar user |
| role | ENUM('owner', 'shared') | Rol van gebruiker |
| status | ENUM('active', 'pending', 'revoked') | Status van toegang |
| invited_by_id | INT (FK, nullable) | Wie heeft uitgenodigd |
| invited_at | DATETIME (nullable) | Wanneer uitgenodigd |
| accepted_at | DATETIME (nullable) | Wanneer geaccepteerd |
| created_at | DATETIME | Aanmaak tijdstip |

**Constraints:**
- UNIQUE constraint op (account_id, user_id)
- Foreign key CASCADE on DELETE voor account_id en user_id
- Foreign key SET NULL on DELETE voor invited_by_id

**Data Migratie:**
- Bestaande relaties in account_users moeten gemigreerd worden als 'owner' met status 'active'

### 2. Entity Updates

**Nieuwe Entity: `src/Entity/AccountUser.php`**
- Join entity met alle velden uit database schema
- Constants voor ROLE_OWNER, ROLE_SHARED
- Constants voor STATUS_ACTIVE, STATUS_PENDING, STATUS_REVOKED
- Helper methods: `isOwner()`, `isActive()`, `isPending()`
- Timestamps: createdAt met default value

**Update Entity: `src/Entity/Account.php`**
- Verander many-to-many relatie naar OneToMany relatie met AccountUser
- Nieuwe methods:
  - `isOwnedBy(User $user): bool` - Check of user owner is (ROLE_OWNER + STATUS_ACTIVE)
  - `hasAccess(User $user): bool` - Check of user toegang heeft (STATUS_ACTIVE, any role)
  - `getOwners(): array` - Return alle active owners
  - `addOwner(User $user): self` - Voeg owner toe (alleen als nog geen toegang)

### 3. Service Layer

**Update: `src/Service/AccountService.php`**

Wijzig `getOrCreateAccountByNumberForUser()` naar:
```php
/**
 * Get or create account - ONLY creates if it doesn't exist
 * If exists but user doesn't own it: THROW EXCEPTION
 */
public function getOrCreateAccountByNumberForUser(string $accountNumber, User $user): Account
{
    $account = $this->accountRepository->findByAccountNumber($accountNumber);
    
    if (!$account) {
        // Account doesn't exist - create and assign to user as owner
        $account = new Account();
        $account->setAccountNumber($accountNumber);
        $account->addOwner($user);
        
        $this->entityManager->persist($account);
        $this->entityManager->flush();
        
        return $account;
    }

    // Account exists - check if user has access
    if (!$account->hasAccess($user)) {
        throw new AccountAccessDeniedException(
            sprintf(
                'Account %s already exists and belongs to another user. ' .
                'If this is a shared account, ask the owner to invite you.',
                $this->maskAccountNumber($accountNumber)
            )
        );
    }

    return $account;
}

private function maskAccountNumber(string $accountNumber): string
{
    if (strlen($accountNumber) <= 8) {
        return str_repeat('*', strlen($accountNumber));
    }
    
    return substr($accountNumber, 0, 4) . 
           str_repeat('*', strlen($accountNumber) - 8) . 
           substr($accountNumber, -4);
}
```

**Nieuw: `src/Service/AccountSharingService.php`**

Service met methods:
- `shareAccount(Account $account, User $owner, User $sharedWith): AccountUser`
  - Verify owner heeft ROLE_OWNER
  - Check of al gedeeld
  - Create AccountUser met ROLE_SHARED en STATUS_PENDING
  - Optioneel: verstuur email uitnodiging
- `acceptInvitation(AccountUser $accountUser, User $user): void`
  - Verify user is correct
  - Check status is PENDING
  - Update naar STATUS_ACTIVE
  - Set acceptedAt timestamp
- `revokeAccess(Account $account, User $owner, User $userToRevoke): void`
  - Verify owner heeft ROLE_OWNER
  - Kan geen ROLE_OWNER revoken
  - Update status naar REVOKED

### 4. Exception Handling

**Nieuw: `src/Exception/AccountAccessDeniedException.php`**
- Extend RuntimeException
- Gebruikt voor unauthorized account access

### 5. API Endpoints

**Nieuw: `src/Controller/Api/AccountSharingController.php`**

Endpoints:
- `POST /api/accounts/{id}/share` - Deel account met user (via email)
  - Input: `{"email": "partner@example.com"}`
  - Verify current user is owner
  - Return invitation details
- `GET /api/accounts/invitations` - Lijst pending invitations voor current user
- `POST /api/accounts/invitations/{id}/accept` - Accepteer uitnodiging
- `DELETE /api/accounts/{id}/users/{userId}/revoke` - Revoke toegang

Alle endpoints require authentication (IsGranted ROLE_USER).

### 6. Repository

**Optioneel Update: `src/Repository/AccountUserRepository.php`**
- Geen specifieke queries nodig, maar kan helper methods bevatten

## TESTING REQUIREMENTS

### Unit Tests: `tests/Service/AccountServiceTest.php`

Minimaal testen:
1. `testCreateNewAccountAssignsOwner()` - Nieuwe account krijgt gebruiker als owner
2. `testCannotClaimExistingAccount()` - Exception bij poging tot claimen bestaand account
3. `testOwnerCanAccessOwnAccount()` - Owner heeft toegang tot eigen account
4. `testSharedUserCanAccessAccount()` - Gedeelde gebruiker heeft toegang

### Integration Tests: `tests/Service/AccountSharingServiceTest.php`

1. `testShareAccountCreatesInvitation()` - Delen maakt pending invitation
2. `testNonOwnerCannotShare()` - Niet-owner kan niet delen
3. `testAcceptInvitationGrantsAccess()` - Accepteren geeft toegang
4. `testRevokeAccessRemovesPermission()` - Revoken verwijdert toegang
5. `testCannotRevokeOwnerAccess()` - Owner access kan niet gerevoked

## BELANGRIJK: BACKWARDS COMPATIBILITY

- Bestaande `Account::addUser()` method mag blijven werken (deprecated)
- Migratie moet alle bestaande relaties behouden
- CSV import functionaliteit mag niet breken voor bestaande users

## ERROR HANDLING

- Gebruik duidelijke error messages
- Mask account numbers in error messages (privacy)
- Log security events (failed access attempts)
- Return appropriate HTTP status codes (400, 403, 404)

## CODE QUALITY

- Follow Symfony best practices
- Use type hints overal
- Add PHPDoc comments voor public methods
- Use dependency injection
- Follow PSR-12 coding standards

## DELIVERABLES

1. ✅ Database migration file
2. ✅ AccountUser entity
3. ✅ Updated Account entity
4. ✅ Updated AccountService
5. ✅ New AccountSharingService
6. ✅ New AccountSharingController
7. ✅ Exception class
8. ✅ Unit tests
9. ✅ Integration tests
10. ✅ Update bestaande CSV import code om nieuwe exception te handlen

## OUT OF SCOPE (NIET DOEN)

- Frontend implementation (alleen API endpoints)
- Email template design
- Notification system
- Activity logging (kan later)
- API rate limiting

## WORKFLOW

1. Start met database migration
2. Create AccountUser entity
3. Update Account entity
4. Fix AccountService (kritisch!)
5. Create AccountSharingService
6. Create API controller
7. Write tests
8. Test manually met Postman/curl

## VRAGEN BIJ ONDUIDELIJKHEDEN

Als er onduidelijkheden zijn:
- Check bestaande code in src/Entity/Account.php
- Check bestaande Account repository
- Check bestaande CSV import implementation
- Vraag om clarificatie

## SUCCESS CRITERIA

✅ Migration runs successfully
✅ Existing data migrated correctly
✅ Old account_users table is dropped
✅ AccountService throws exception voor unauthorized access
✅ Account sharing workflow works via API
✅ All tests pass
✅ No breaking changes voor bestaande functionaliteit
