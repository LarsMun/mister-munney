# API Documentation

**Last Updated:** January 2026

This document describes how to access and use the API documentation for Mister Munney.

## OpenAPI / Swagger Documentation

The application provides comprehensive API documentation using OpenAPI 3.0 (Swagger).

### Access URLs

| Environment | Swagger UI URL |
|-------------|----------------|
| **Production** | https://munney.munne.me/api/doc |
| **Development** | https://devmunney.home.munne.me/api/doc |
| **Local** | http://localhost:18787/api/doc |

### Features

The Swagger UI provides:
- **Interactive documentation** for all API endpoints
- **Try-it-out functionality** to test endpoints directly
- **Request/Response schemas** with validation rules
- **Authentication integration** for JWT testing

### API Endpoints Overview

The API is organized into the following sections:

| Tag | Description |
|-----|-------------|
| **Authentication** | Login, register, JWT token management |
| **Accounts** | Bank account management and sharing |
| **Transactions** | Transaction CRUD, import, and filtering |
| **Categories** | Category management and statistics |
| **Patterns** | Transaction categorization patterns |
| **Budgets** | Budget creation and tracking |
| **Forecasts** | Financial forecasting |
| **System** | Health checks and system status |

### Authentication

Most API endpoints require JWT authentication. To authenticate:

1. **Login** via `POST /api/login` with email and password
2. **Receive JWT token** in the response
3. **Include token** in subsequent requests: `Authorization: Bearer <token>`

In Swagger UI:
1. Click the "Authorize" button
2. Enter your JWT token
3. All subsequent requests will include the token

### Health Check Endpoints

The following public endpoints are available for monitoring:

| Endpoint | Description |
|----------|-------------|
| `GET /api/health` | Full health check (database, JWT keys) |
| `GET /api/health/live` | Liveness probe (application running) |
| `GET /api/health/ready` | Readiness probe (database accessible) |

Example response from `/api/health`:
```json
{
  "status": "healthy",
  "checks": {
    "database": "ok",
    "jwt_keys": "ok"
  },
  "timestamp": "2026-01-19T20:30:00+00:00"
}
```

### API Versioning

Currently, the API does not use versioning (all endpoints are at `/api/*`). Future versions may introduce path-based versioning (`/api/v2/*`).

### Rate Limiting

API requests are rate-limited to prevent abuse:
- **API endpoints**: 100 requests per minute per IP
- **Login endpoint**: 5 attempts per 5 minutes per IP (with exponential backoff)

Rate limit headers are included in responses:
```
X-RateLimit-Limit: 100
X-RateLimit-Remaining: 95
X-RateLimit-Reset: 1705700000
```

### Error Responses

The API uses standard HTTP status codes:

| Status | Description |
|--------|-------------|
| `200` | Success |
| `201` | Created |
| `400` | Bad Request (validation error) |
| `401` | Unauthorized (missing/invalid token) |
| `403` | Forbidden (insufficient permissions) |
| `404` | Not Found |
| `429` | Too Many Requests (rate limited) |
| `500` | Internal Server Error |

Error responses include a JSON body:
```json
{
  "error": "Error type",
  "message": "Human-readable description"
}
```

### OpenAPI Annotations

The API documentation is generated from PHP annotations using the `nelmio/api-doc-bundle` package. Over 1,000 OpenAPI annotations are used to document:

- Request parameters and bodies
- Response schemas and examples
- Authentication requirements
- Validation rules

To add documentation for a new endpoint, use OpenAPI attributes:

```php
#[OA\Get(
    path: '/api/resource',
    summary: 'Get resource description',
    tags: ['Resources'],
    responses: [
        new OA\Response(
            response: 200,
            description: 'Success response',
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'id', type: 'integer'),
                    new OA\Property(property: 'name', type: 'string'),
                ]
            )
        ),
    ]
)]
```

## JSON Export

The OpenAPI specification can be exported as JSON for use with other tools:

```
GET /api/doc.json
```

This is useful for:
- Generating client SDKs
- Importing into Postman/Insomnia
- Integration testing
- CI/CD validation

## Related Documentation

- [01_PROJECT_OVERVIEW.md](01_PROJECT_OVERVIEW.md) - Application architecture
- [06_TESTING_GUIDE.md](06_TESTING_GUIDE.md) - API testing guide
- [08_QUICK_REFERENCE.md](08_QUICK_REFERENCE.md) - Common API commands
