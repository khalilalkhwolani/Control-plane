# Pro One Control Center — React UI

This is the active operator interface for Pro One. Laravel remains the only
authority for authentication, permissions, customer/installation records, audit
events, and license signing. React consumes Laravel's `/api/v1` contract and
never receives the private signing key.

For local development, run the Laravel API on port `8090` and this Vite app on
port `5190`. The Vite proxy forwards `/api` and `/sanctum` to Laravel.

The old Blade screens in `control-plane/backend/resources/views` remain a
temporary fallback while the React cutover is verified; they are not the active
development surface.
