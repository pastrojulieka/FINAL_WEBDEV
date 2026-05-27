# Mobile App Testing & Troubleshooting Guide

## What Was Fixed

### 1. Registration Endpoint (`POST /api/register`)
**Improvements Made:**
- Added comprehensive try-catch error handling
- Added validation error logging  
- Returns detailed error messages for each validation failure
- Checks for duplicate email registration
- Handles email sending failures gracefully (registration succeeds even if email fails to send)
- Returns complete user object with verification status

### 2. Order Creation Endpoint (`POST /api/orders`)
**Improvements Made:**
- Added comprehensive try-catch error handling
- Added validation for quantity (must be > 0)
- Returns detailed error messages for insufficient stock
- Logs successful order creation for debugging
- Returns complete order details in response
- Handles database schema properly with `total_amount` column

### 3. Database Migration
**What Was Fixed:**
- Created migration to drop old `total_price` column (causing SQLSTATE[HY000] error)
- Drops old `status` and `order_date` columns that were incompatible
- Ensures `delivery_date` allows NULL values
- **Status**: Migration deployed to Railway (pushed to GitHub)

---

## Testing the Mobile App Features

### Prerequisites
1. Application is running (Docker Compose or local development)
2. Admin has created at least one product
3. Product has sufficient quantity/stock

### Test 1: Registration

**Using Postman or cURL:**

```bash
curl -X POST http://localhost:8000/api/register \
  -H "Content-Type: application/json" \
  -d '{
    "email": "testuser@example.com",
    "password": "testpass123"
  }'
```

**Expected Success Response (201):**
```json
{
  "success": true,
  "message": "Registration successful. Please check your email to verify your account.",
  "user": {
    "id": 5,
    "email": "testuser@example.com",
    "isVerified": false,
    "roles": ["ROLE_USER"]
  }
}
```

**Test Scenarios:**

| Scenario | Input | Expected Status | Expected Message |
|----------|-------|------------------|-----------------|
| Valid registration | email: test@example.com, password: pass123 | 201 | Registration successful |
| Missing email | password: pass123 | 400 | Email and password are required |
| Missing password | email: test@example.com | 400 | Email and password are required |
| Invalid email | email: invalid-email, password: pass123 | 400 | Invalid email address |
| Short password | email: test@example.com, password: 123 | 400 | Password must be at least 6 characters |
| Duplicate email | email: admin@gmail.com, password: pass123 | 409 | Email already registered |

---

### Test 2: Login

**Using Postman or cURL:**

```bash
curl -X POST http://localhost:8000/api/login \
  -H "Content-Type: application/json" \
  -d '{
    "email": "testuser@example.com",
    "password": "testpass123"
  }'
```

**Expected Success Response (200):**
```json
{
  "success": true,
  "message": "Login successful",
  "token": "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9...",
  "user": {
    "id": 5,
    "email": "testuser@example.com",
    "roles": ["ROLE_USER"],
    "isVerified": true
  }
}
```

**Important Notes:**
- User must verify email first before login (if not verified, error: "Please verify your email address before logging in")
- For development testing, manually set `is_verified = 1` in the `user` table to skip email verification
- Save the `token` value for the next test

---

### Test 3: Create Order

**Using Postman or cURL:**

```bash
curl -X POST http://localhost:8000/api/orders \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer <JWT_TOKEN>" \
  -d '{
    "product_id": 1,
    "quantity": 2,
    "customer_name": "John Doe",
    "material": "Cotton",
    "color": "Blue"
  }'
```

Replace `<JWT_TOKEN>` with the token received from login.

**Expected Success Response (201):**
```json
{
  "success": true,
  "message": "Order created successfully",
  "data": {
    "id": 10,
    "customer_name": "John Doe",
    "product_name": "Product Name",
    "material": "Cotton",
    "color": "Blue",
    "quantity": 2,
    "price": 29.99,
    "total_amount": 59.98,
    "date": "2025-05-28 14:30:00",
    "delivery_date": "2025-06-04"
  }
}
```

**Test Scenarios:**

| Scenario | Expected Status | Expected Error |
|----------|------------------|----------------|
| Valid order | 201 | N/A - Success |
| Missing product_id | 400 | Product ID, quantity, and customer_name are required |
| Missing quantity | 400 | Product ID, quantity, and customer_name are required |
| Missing customer_name | 400 | Product ID, quantity, and customer_name are required |
| Quantity = 0 | 400 | Quantity must be greater than 0 |
| Quantity negative | 400 | Quantity must be greater than 0 |
| Non-existent product | 404 | Product not found |
| Insufficient stock | 400 | Insufficient stock available. Available: X, Requested: Y |
| No auth token | 401 | Authentication required |
| Invalid/expired token | 401 | Unauthorized (JWT error) |

---

## Troubleshooting

### Issue: "Email already registered"

**Cause**: The email address is already in the system

**Solution**:
- Use a different email address
- Or check database if old test accounts can be deleted

**Debug**: Check `user` table for existing email

```sql
SELECT * FROM user WHERE email = 'testuser@example.com';
```

---

### Issue: "Please verify your email address before logging in"

**Cause**: User account exists but email hasn't been verified

**Solution - Development Only**:
Update the database to mark user as verified:
```sql
UPDATE user SET is_verified = 1 WHERE email = 'testuser@example.com';
```

**Solution - Production**:
- User must click the verification link in their email
- If email not received, check spam folder or resend verification email

---

### Issue: "Authentication required" when creating order

**Cause**: JWT token is missing, invalid, or expired

**Solution**:
1. Ensure you logged in successfully and got a token
2. Include token in Authorization header: `Authorization: Bearer <token>`
3. Check token format - should start with "eyJ"
4. If token expired (typically 1 hour), login again to get new token

**Debug**: Test without order endpoint first:
```bash
curl -X GET http://localhost:8000/api/orders \
  -H "Authorization: Bearer <JWT_TOKEN>"
```

---

### Issue: "Insufficient stock available"

**Cause**: Product doesn't have enough quantity for the requested amount

**Solution**:
- Reduce the order quantity
- Or add more stock to the product via admin panel
- Check product quantity: `GET /api/products/{id}`

**Debug**: View product details to see available stock:
```bash
curl http://localhost:8000/api/products/1
```

---

### Issue: Order creation returns database error

**Possible Cause**: Migration hasn't run on Railway yet

**Solution**:
1. Check migration status:
   ```bash
   php bin/console doctrine:migrations:status
   ```

2. If migration not run, execute it:
   ```bash
   php bin/console doctrine:migrations:migrate
   ```

3. Verify `order` table has `total_amount` column:
   ```sql
   DESCRIBE order;
   ```

**Expected columns**: id, customer_name, product_name, material, color, quantity, price, total_amount, date, delivery_date, created_by_id

---

### Issue: CORS error from mobile app

**Cause**: Mobile app domain not allowed by CORS configuration

**Error Message**: "Access to XMLHttpRequest has been blocked by CORS policy"

**Solution**:
Check CORS configuration in `config/packages/nelmio_cors.yaml`:
```yaml
allow_origin: ['%env(CORS_ALLOW_ORIGIN)%']
```

Update `.env` file:
```
CORS_ALLOW_ORIGIN=http://localhost:3000
```

For mobile apps, might need to use:
```
CORS_ALLOW_ORIGIN=.*
```

---

### Issue: Email verification not working

**Debug Steps**:
1. Check if EmailVerificationService is properly configured
2. Verify `mailer` settings in `.env`:
   ```
   MAILER_DSN=smtp://...
   ```

3. Check email service logs:
   ```bash
   docker compose logs app | grep -i email
   ```

4. For development, manually verify user:
   ```sql
   UPDATE user SET is_verified = 1, verification_token = NULL WHERE email = 'testuser@example.com';
   ```

---

## Test Data Setup

### Create Test User via SQL

```sql
INSERT INTO user (email, password, roles, is_verified, verification_token) 
VALUES (
  'testuser@gmail.com',
  '$2y$13$...hashed_password_here...',
  '["ROLE_USER"]',
  1,
  NULL
);
```

### Create Test Product via Admin

1. Login as admin: `admin@gmail.com` / `admin123`
2. Go to `/admin/product/new`
3. Fill in:
   - Name: "Test Product"
   - Price: 29.99
   - Description: "Test product for mobile app"
   - Image: Upload image
   - Material: "Cotton"
   - Color: "Blue"
   - Quantity: 100
4. Submit form

---

## API Endpoints Summary

| Method | Endpoint | Auth | Purpose |
|--------|----------|------|---------|
| POST | /api/register | ❌ | Register new user |
| POST | /api/login | ❌ | Login and get JWT token |
| GET | /api/products | ❌ | Get all products |
| GET | /api/products/{id} | ❌ | Get single product |
| POST | /api/orders | ✅ | Create order |
| GET | /api/orders | ✅ | Get user orders |
| GET | /api/orders/{id} | ✅ | Get single order |

✅ = Requires JWT token in Authorization header

---

## Performance Checklist

- [ ] Test registration with new email
- [ ] Verify user created in database
- [ ] Test login with new account
- [ ] Verify JWT token received
- [ ] Test product listing
- [ ] Create order with valid product_id and quantity
- [ ] Verify order created in database with correct total_amount
- [ ] Test insufficient stock error
- [ ] Test with invalid JWT token
- [ ] Test CORS from mobile app domain
- [ ] Verify email notifications (if applicable)

---

## Debugging Tips

### Enable detailed logging:

Update `config/packages/monolog.yaml` to log API requests:
```yaml
monolog:
    channels: [api]
    handlers:
        api:
            type: stream
            path: '%kernel.logs_dir%/api.log'
            level: debug
            channels: [api]
```

Then check logs:
```bash
tail -f var/log/api.log
```

### Docker Compose logs:

```bash
# All logs
docker compose logs -f

# Just PHP app
docker compose logs -f app

# Just database
docker compose logs -f db
```

### Database inspection:

```bash
# Connect to database
docker compose exec db mysql -u root -p${MYSQL_ROOT_PASSWORD} ${MYSQL_DATABASE}

# Check migrations
SELECT * FROM doctrine_migration_versions;

# Check users
SELECT id, email, is_verified FROM user;

# Check orders
SELECT * FROM `order` ORDER BY id DESC LIMIT 5;

# Check products
SELECT id, name, price, quantity FROM product LIMIT 5;
```

---

## Next Steps

1. **Test locally** using Postman or cURL with the test scenarios above
2. **Fix any issues** by checking error messages and logs
3. **Deploy to Railway** - Changes are already pushed, migrations will run automatically
4. **Test on mobile app** once deployed and working locally
5. **Monitor logs** on Railway for any runtime errors

For production issues, check Railway logs and database to diagnose problems.
