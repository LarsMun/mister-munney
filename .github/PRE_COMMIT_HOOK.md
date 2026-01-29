# Pre-Commit Hook Setup

This project includes a local pre-commit hook that runs CI checks before allowing commits. This ensures code quality and catches issues early.

## ⚙️ Setup

The pre-commit hook is already installed in `.git/hooks/pre-commit` (not tracked by git, stays local).

If you need to reinstall it:

```bash
# From project root
cp .github/pre-commit-hook.sh .git/hooks/pre-commit
chmod +x .git/hooks/pre-commit
```

## 🔍 What It Checks

### Backend Changes:
1. ✅ PHP platform requirements
2. 🔒 Security vulnerabilities (`composer audit`)
3. 📋 composer.json validation
4. 🧪 PHPUnit unit tests
5. 🔬 Symfony container integrity

### Frontend Changes:
1. 📘 TypeScript type checking
2. 🧹 ESLint code quality
3. 🧪 Unit tests with coverage
4. 🏗️ Build check (disabled by default for speed)

## 🚀 Usage

The hook runs automatically on every commit:

```bash
git commit -m "feat: Add new feature"
# Hook runs all checks...
# ✅ All checks pass → commit succeeds
# ❌ Checks fail → commit blocked
```

## ⚡ Bypass (Emergency Only)

If you need to commit urgently (NOT recommended):

```bash
git commit --no-verify -m "emergency fix"
```

**Warning**: CI will still run these checks on the remote, so bypassing locally just delays the feedback.

## 🐛 Troubleshooting

### "Backend container is not running"
```bash
docker-compose up -d backend database
```

### "Permission denied"
```bash
chmod +x .git/hooks/pre-commit
```

### Hook not running
Check that the file exists and is executable:
```bash
ls -la .git/hooks/pre-commit
```

## 📝 Notes

- The hook only checks files you've staged for commit
- If you only change backend files, frontend checks are skipped (and vice versa)
- Build check is disabled by default to save time (~30 seconds)
- The hook matches exactly what CI runs, so passing locally = passing on GitHub

## 🔧 Customization

Edit `.git/hooks/pre-commit` to:
- Enable/disable specific checks
- Change timeout values
- Add custom validations
- Enable full build check

Remember: Changes to `.git/hooks/pre-commit` are local only. Share updates by modifying this documentation.
