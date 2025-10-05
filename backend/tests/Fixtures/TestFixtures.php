<?php

namespace App\Tests\Fixtures;

use App\Entity\Account;
use App\Entity\Category;
use App\Entity\Transaction;
use App\Enum\TransactionType;
use App\Money\MoneyFactory;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Money\Money;

class TestFixtures extends Fixture
{
    private MoneyFactory $moneyFactory;

    public function __construct(MoneyFactory $moneyFactory)
    {
        $this->moneyFactory = $moneyFactory;
    }

    public function load(ObjectManager $manager): void
    {
        // Create test account
        $account = new Account();
        $account->setName('Test Account')
            ->setAccountNumber('NL91RABO0123456789')
            ->setIsDefault(true);
        $manager->persist($account);

        // Create test categories
        $groceries = new Category();
        $groceries->setName('Groceries')
            ->setColor('#22C55E')
            ->setIcon('shopping-cart');
        $manager->persist($groceries);

        $salary = new Category();
        $salary->setName('Salary')
            ->setColor('#3B82F6')
            ->setIcon('dollar-sign');
        $manager->persist($salary);

        // Create test transactions
        $transactions = [
            [
                'description' => 'Albert Heijn Supermarket',
                'amount' => -2550, // €25.50
                'type' => TransactionType::DEBIT,
                'category' => $groceries,
                'date' => new \DateTime('2024-01-15')
            ],
            [
                'description' => 'Salary Payment Company XYZ',
                'amount' => 300000, // €3000.00
                'type' => TransactionType::CREDIT,
                'category' => $salary,
                'date' => new \DateTime('2024-01-01')
            ],
            [
                'description' => 'Jumbo Supermarket',
                'amount' => -1875, // €18.75
                'type' => TransactionType::DEBIT,
                'category' => $groceries,
                'date' => new \DateTime('2024-01-20')
            ]
        ];

        $runningBalance = Money::EUR(100000); // Start with €1000.00

        foreach ($transactions as $txData) {
            $transaction = new Transaction();
            $amount = Money::EUR($txData['amount']);

            $transaction->setHash(md5($txData['description'] . $txData['date']->format('Y-m-d')))
                ->setDate($txData['date'])
                ->setDescription($txData['description'])
                ->setAccount($account)
                ->setTransactionType($txData['type'])
                ->setAmount($amount)
                ->setMutationType('Online Banking')
                ->setNotes('Test transaction')
                ->setCategory($txData['category']);

            // Calculate balance after transaction
            if ($txData['type'] === TransactionType::CREDIT) {
                $runningBalance = $runningBalance->add($amount);
            } else {
                $runningBalance = $runningBalance->subtract($amount->absolute());
            }

            $transaction->setBalanceAfter($runningBalance);

            $manager->persist($transaction);
        }

        $manager->flush();
    }
}