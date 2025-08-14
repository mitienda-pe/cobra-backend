# Database Setup Instructions

This document provides instructions for setting up and recreating the database with all necessary Ligo configuration fields.

## Database Migration for Ligo Configuration

### Overview
The Ligo configuration requires additional fields in the `superadmin_ligo_config` table to support comprehensive debtor and transaction settings.

### Migration Files
- **Migration**: `app/Database/Migrations/2025-08-14-191120_AddDebtorTransactionFieldsToSuperadminLigoConfig.php`
- **Command**: `app/Commands/ApplyLigoConfigMigration.php`

### New Fields Added
The migration adds the following fields to `superadmin_ligo_config`:

1. **debtor_phone_number** (VARCHAR 20, nullable)
   - Phone number for debtor (different from mobile)

2. **debtor_type_of_person** (VARCHAR 5, default 'N')
   - Type of person: N=Natural, J=Juridica

3. **creditor_address_line** (VARCHAR 255, default 'JR LIMA')
   - Default address line for creditors

4. **transaction_type** (VARCHAR 10, default '320')
   - Default transaction type for transfers

5. **channel** (VARCHAR 10, default '15')
   - Default channel for transfers

## How to Apply Migration

### Option 1: Using Custom Command (Recommended)
```bash
# Check migration status and apply safely
php spark ligo:migrate

# Force migration even if some fields exist
php spark ligo:migrate --force
```

### Option 2: Using Standard CodeIgniter Migration
```bash
# Run all pending migrations
php spark migrate

# Check migration status
php spark migrate:status
```

### Option 3: Manual Database Recreation
If you need to recreate the database from scratch:

```bash
# 1. Backup current data (if needed)
sqlite3 writable/db/cobranzas.db ".backup backup_$(date +%Y%m%d_%H%M%S).db"

# 2. Remove current database
rm writable/db/cobranzas.db

# 3. Run all migrations from scratch
php spark migrate

# 4. Seed initial data (if you have seeders)
php spark db:seed
```

## Verification

### Check Fields Exist
```bash
# List all fields in superadmin_ligo_config table
sqlite3 writable/db/cobranzas.db "PRAGMA table_info(superadmin_ligo_config);"
```

### Expected Fields
After migration, the table should contain these fields:
- id, config_key, environment, username, password, company_id, account_id
- merchant_code, private_key, webhook_secret, auth_url, api_url
- ssl_verify, enabled, is_active, notes, created_at, updated_at, deleted_at
- debtor_name, debtor_id, debtor_id_code, debtor_address_line
- debtor_mobile_number, debtor_participant_code
- **NEW**: debtor_phone_number, debtor_type_of_person, creditor_address_line, transaction_type, channel

## Configuration After Migration

1. Access: `/superadmin/ligo-config/edit/{id}`
2. Fill in the new debtor and transaction fields
3. Test the configuration using the transfer functionality

## Rollback

If you need to remove the new fields:
```bash
# This will run the down() method of the migration
php spark migrate:rollback
```

## Production Deployment

The migration is designed to be safe for production:
- Uses `ALTER TABLE ADD COLUMN` which is non-destructive
- Provides default values for new fields
- Includes proper rollback functionality
- Can be run multiple times safely

## Notes

- The migration includes proper defaults for all new fields
- Existing configurations will continue to work without modification
- The custom command provides safety checks to prevent conflicts
- All changes are reversible using the rollback functionality