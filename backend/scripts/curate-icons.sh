#!/bin/bash
# Script to keep only budget/finance-relevant icons

ICONS_DIR="backend/public/icons"

# Create backup directory
BACKUP_DIR="backend/public/icons_backup_$(date +%Y%m%d_%H%M%S)"
mkdir -p "$BACKUP_DIR"

echo "Backing up all icons to $BACKUP_DIR..."
cp -r "$ICONS_DIR"/*.svg "$BACKUP_DIR/" 2>/dev/null || true

# List of budget/finance-relevant icons to KEEP
# Organized by category for clarity
KEEP_ICONS=(
    # Money & Finance
    "bank-thin.svg"
    "coin-thin.svg"
    "coin-vertical-thin.svg"
    "coins-thin.svg"
    "credit-card-thin.svg"
    "currency-btc-thin.svg"
    "currency-circle-dollar-thin.svg"
    "currency-cny-thin.svg"
    "currency-dollar-simple-thin.svg"
    "currency-dollar-thin.svg"
    "currency-eth-thin.svg"
    "currency-eur-thin.svg"
    "currency-gbp-thin.svg"
    "currency-inr-thin.svg"
    "currency-jpy-thin.svg"
    "currency-krw-thin.svg"
    "currency-kzt-thin.svg"
    "currency-ngn-thin.svg"
    "currency-rub-thin.svg"
    "hand-coins-thin.svg"
    "money-thin.svg"
    "money-wavy-thin.svg"
    "piggy-bank-thin.svg"
    "receipt-thin.svg"
    "receipt-x-thin.svg"
    "vault-thin.svg"
    "wallet-thin.svg"
    "invoice-thin.svg"
    "cash-register-thin.svg"

    # Shopping & Retail
    "shopping-bag-thin.svg"
    "shopping-bag-open-thin.svg"
    "shopping-cart-thin.svg"
    "shopping-cart-simple-thin.svg"
    "storefront-thin.svg"
    "bag-thin.svg"
    "bag-simple-thin.svg"
    "basket-thin.svg"
    "handbag-thin.svg"
    "handbag-simple-thin.svg"

    # Food & Dining
    "coffee-thin.svg"
    "coffee-bean-thin.svg"
    "fork-knife-thin.svg"
    "knife-thin.svg"
    "hamburger-thin.svg"
    "pizza-thin.svg"
    "bowl-food-thin.svg"
    "carrot-thin.svg"
    "tea-bag-thin.svg"

    # Transportation
    "car-thin.svg"
    "car-simple-thin.svg"
    "car-profile-thin.svg"
    "bus-thin.svg"
    "train-thin.svg"
    "train-simple-thin.svg"
    "train-regional-thin.svg"
    "airplane-thin.svg"
    "airplane-in-flight-thin.svg"
    "airplane-takeoff-thin.svg"
    "airplane-landing-thin.svg"
    "gas-pump-thin.svg"
    "gas-can-thin.svg"
    "cable-car-thin.svg"

    # Housing & Utilities
    "house-thin.svg"
    "house-simple-thin.svg"
    "house-line-thin.svg"
    "warehouse-thin.svg"
    "lighthouse-thin.svg"
    "paint-brush-household-thin.svg"

    # Health & Fitness
    "hospital-thin.svg"

    # Entertainment & Leisure
    "game-controller-thin.svg"
    "gift-thin.svg"
    "music-note-thin.svg"
    "music-note-simple-thin.svg"
    "music-notes-thin.svg"
    "basketball-thin.svg"
    "court-basketball-thin.svg"

    # Communication & Technology
    "phone-thin.svg"
    "sim-card-thin.svg"
    "headphones-thin.svg"

    # Education & Books
    "book-thin.svg"
    "book-open-thin.svg"
    "books-thin.svg"
    "notebook-thin.svg"
    "certificate-thin.svg"

    # Clothing
    "t-shirt-thin.svg"
    "shirt-folded-thin.svg"

    # Pets
    "dog-thin.svg"
    "cat-thin.svg"

    # Other Useful
    "baby-carriage-thin.svg"
    "identification-card-thin.svg"
    "presentation-chart-thin.svg"
    "chart-scatter-thin.svg"

    # General utility icons
    "arrow-clockwise-thin.svg"
    "arrow-counter-clockwise-thin.svg"
    "check-circle-thin.svg"
    "x-circle-thin.svg"
    "plus-circle-thin.svg"
    "minus-circle-thin.svg"
    "star-thin.svg"
    "heart-thin.svg"
    "calendar-thin.svg"
    "clock-thin.svg"
)

echo "Keeping ${#KEEP_ICONS[@]} relevant icons..."

# Remove all icons first
cd "$ICONS_DIR"
rm -f *.svg

# Restore only the icons we want to keep
cd - > /dev/null
for icon in "${KEEP_ICONS[@]}"; do
    if [ -f "$BACKUP_DIR/$icon" ]; then
        cp "$BACKUP_DIR/$icon" "$ICONS_DIR/"
        echo "✓ Kept: $icon"
    else
        echo "✗ Not found: $icon"
    fi
done

echo ""
echo "Done! Curated to ${#KEEP_ICONS[@]} icons."
echo "Original icons backed up to: $BACKUP_DIR"
echo ""
echo "Icons remaining:"
ls "$ICONS_DIR"/*.svg 2>/dev/null | wc -l
