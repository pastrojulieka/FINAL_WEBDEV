# Mobile App API Documentation

This document outlines the API endpoints required for mobile app integration.

## Base URL
- **Local**: `http://localhost:8000`
- **Production**: `https://FINAL_WEBDEV-production.up.railway.app`

## Authentication
The API uses JWT (JSON Web Token) authentication for protected endpoints.

### JWT Token Usage
Include the token in the `Authorization` header:
```
Authorization: Bearer <jwt_token>
```

---

## 1. User Registration

**Endpoint**: `POST /api/register`

**Description**: Register a new user for the mobile app.

**Request Body**:
```json
{
  "email": "user@example.com",
  "password": "password123"
}
```

**Validation Rules**:
- Email must be valid and unique
- Password must be at least 6 characters long

**Success Response (201)**:
```json
{
  "success": true,
  "message": "Registration successful. Please check your email to verify your account.",
  "user": {
    "id": 1,
    "email": "user@example.com",
    "isVerified": false,
    "roles": ["ROLE_USER"]
  }
}
```

**Error Response (400)**:
```json
{
  "success": false,
  "message": "Email already registered"
}
```

**Error Response (400 - Validation)**:
```json
{
  "success": false,
  "message": "Validation failed",
  "errors": ["Invalid email format"]
}
```

**Notes**:
- User receives a verification email after registration
- User must verify email before login (via link in email)
- Email verification can be skipped in development by manually updating `is_verified` in database

---

## 2. User Login

**Endpoint**: `POST /api/login`

**Description**: Authenticate user and receive JWT token.

**Request Body**:
```json
{
  "email": "user@example.com",
  "password": "password123"
}
```

**Success Response (200)**:
```json
{
  "success": true,
  "message": "Login successful",
  "token": "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9...",
  "user": {
    "id": 1,
    "email": "user@example.com",
    "roles": ["ROLE_USER"],
    "isVerified": true
  }
}
```

**Error Response (401)**:
```json
{
  "success": false,
  "message": "Invalid credentials"
}
```

**Error Response (403 - Not Verified)**:
```json
{
  "success": false,
  "message": "Please verify your email address before logging in",
  "verified": false
}
```

**Notes**:
- Use the returned token for subsequent API requests
- Token expires after configured time (typically 1 hour)

---

## 3. Get All Products

**Endpoint**: `GET /api/products`

**Description**: Retrieve all available products.

**Authentication**: Not required

**Success Response (200)**:
```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "name": "Product 1",
      "price": 29.99,
      "description": "Product description",
      "image": "image_url.jpg",
      "material": "Cotton",
      "color": "Blue",
      "quantity": 100
    }
  ]
}
```

---

## 4. Get Single Product

**Endpoint**: `GET /api/products/{id}`

**Description**: Retrieve details of a specific product.

**Authentication**: Not required

**Success Response (200)**:
```json
{
  "success": true,
  "data": {
    "id": 1,
    "name": "Product 1",
    "price": 29.99,
    "description": "Product description",
    "image": "image_url.jpg",
    "material": "Cotton",
    "color": "Blue",
    "quantity": 100
  }
}
```

**Error Response (404)**:
```json
{
  "success": false,
  "message": "Product not found"
}
```

---

## 5. Create Order

**Endpoint**: `POST /api/orders`

**Description**: Place a new order for a product.

**Authentication**: Required (JWT token)

**Request Body**:
```json
{
  "product_id": 1,
  "quantity": 5,
  "customer_name": "John Doe",
  "material": "Cotton",
  "color": "Blue"
}
```

**Required Fields**:
- `product_id`: ID of the product to order
- `quantity`: Number of units (must be > 0)
- `customer_name`: Name of the customer

**Optional Fields**:
- `material`: Product material (defaults to product's material)
- `color`: Product color (defaults to product's color)

**Success Response (201)**:
```json
{
  "success": true,
  "message": "Order created successfully",
  "data": {
    "id": 10,
    "customer_name": "John Doe",
    "product_name": "Product 1",
    "material": "Cotton",
    "color": "Blue",
    "quantity": 5,
    "price": 29.99,
    "total_amount": 149.95,
    "date": "2025-05-28 14:30:00",
    "delivery_date": "2025-06-04"
  }
}
```

**Error Response (400 - Insufficient Stock)**:
```json
{
  "success": false,
  "message": "Insufficient stock available. Available: 10, Requested: 20"
}
```

**Error Response (401 - Not Authenticated)**:
```json
{
  "success": false,
  "message": "Authentication required"
}
```

**Error Response (404 - Product Not Found)**:
```json
{
  "success": false,
  "message": "Product not found"
}
```

---

## 6. Get User Orders

**Endpoint**: `GET /api/orders`

**Description**: Retrieve all orders by the current user.

**Authentication**: Required (JWT token)

**Success Response (200)**:
```json
{
  "success": true,
  "data": [
    {
      "id": 10,
      "customer_name": "John Doe",
      "product_name": "Product 1",
      "quantity": 5,
      "total_amount": 149.95,
      "date": "2025-05-28 14:30:00",
      "delivery_date": "2025-06-04",
      "status": "pending"
    }
  ]
}
```

**Error Response (401)**:
```json
{
  "success": false,
  "message": "Authentication required"
}
```

---

## Common Error Codes

| Code | Description |
|------|-------------|
| 400 | Bad Request - Invalid JSON or missing required fields |
| 401 | Unauthorized - Missing or invalid JWT token |
| 403 | Forbidden - User not verified or insufficient permissions |
| 404 | Not Found - Resource doesn't exist |
| 409 | Conflict - Email already registered |
| 500 | Internal Server Error - Server-side error |

---

## Mobile App Implementation Flow

### Registration & Login Flow:
1. **Register**: POST `/api/register` with email and password
2. **Verify Email**: Click link in verification email
3. **Login**: POST `/api/login` with email and password
4. **Store Token**: Save JWT token in mobile app (localStorage, SharedPreferences, etc.)

### Product & Order Flow:
1. **Get Products**: GET `/api/products` (no auth required)
2. **Select Product**: View product details
3. **Create Order**: POST `/api/orders` with product_id, quantity, customer_name
4. **Include Token**: Add JWT token to Authorization header

### Example JavaScript/Fetch:
```javascript
// Register
fetch('http://localhost:8000/api/register', {
  method: 'POST',
  headers: { 'Content-Type': 'application/json' },
  body: JSON.stringify({ email: 'user@example.com', password: 'password123' })
});

// Login
fetch('http://localhost:8000/api/login', {
  method: 'POST',
  headers: { 'Content-Type': 'application/json' },
  body: JSON.stringify({ email: 'user@example.com', password: 'password123' })
});

// Get Products
fetch('http://localhost:8000/api/products');

// Create Order
fetch('http://localhost:8000/api/orders', {
  method: 'POST',
  headers: {
    'Content-Type': 'application/json',
    'Authorization': 'Bearer ' + token
  },
  body: JSON.stringify({
    product_id: 1,
    quantity: 5,
    customer_name: 'John Doe',
    material: 'Cotton',
    color: 'Blue'
  })
});
```

---

## Troubleshooting

### Registration fails with "Email already registered"
- Check if email is already in database
- Use a different email address

### Login fails with "Please verify your email"
- Check email for verification link
- Click verification link to activate account
- For development, manually update `is_verified = 1` in users table

### Order creation fails with "Authentication required"
- Ensure JWT token is included in Authorization header
- Check if token has expired (request login again)
- Format: `Authorization: Bearer <token>`

### Order creation fails with "Insufficient stock"
- Check available quantity for product
- Reduce order quantity
- Add more stock via admin panel

### Order creation fails with database errors
- Check if migrations have run: `php bin/console doctrine:migrations:status`
- Run pending migrations: `php bin/console doctrine:migrations:migrate`
- Verify `order` table exists and has `total_amount` column
