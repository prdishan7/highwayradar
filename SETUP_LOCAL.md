# Local Development Setup Guide

This guide will help you run the Highway GBBS project locally using WAMP for the backend and Vite for the frontend.

## Prerequisites

- WAMP Server installed and running (Apache + MySQL + PHP)
- Node.js and npm installed
- Project is located at `C:\wamp64\www\highway`

## Step 1: Database Setup

1. Open phpMyAdmin (usually at `http://localhost/phpmyadmin`)
2. Create a new database or use the existing one
3. Import the database schema:
   - Click on your database
   - Go to the "SQL" tab
   - Copy and paste the contents of `setup-database.sql`
   - Click "Go" to execute

   **OR** run the SQL file directly:
   ```sql
   -- The database will be created automatically if it doesn't exist
   -- Default database name: highway_gbbs
   ```

4. Update database credentials in `api/config.php` if needed:
   ```php
   'db' => [
       'host' => '127.0.0.1',
       'name' => 'highway_gbbs',
       'user' => 'root',        // Default WAMP MySQL user
       'pass' => '',            // Default WAMP MySQL password (usually empty)
       'charset' => 'utf8mb4',
   ],
   ```

## Step 2: Configure PHP API

The API is already configured to work with WAMP. Verify these settings in `api/config.php`:

- Database credentials match your WAMP MySQL setup
- JWT secret is set (default is fine for local dev)
- Firebase database URL is configured (if using Firebase features)

**Default Admin Credentials:**
- Email: `admin@gmail.com`
- Password: `admin123`

The admin user will be automatically created on the first API request.

## Step 3: Start WAMP Services

1. Open WAMP Server
2. Ensure all services are running (Apache and MySQL should be green)
3. The API will be accessible at: `http://localhost/highway/api/`

Test the API by visiting: `http://localhost/highway/api/status` (should return JSON)

## Step 4: Setup Frontend

1. Navigate to the app directory:
   ```bash
   cd app
   ```

2. Install dependencies (if not already installed):
   ```bash
   npm install
   ```

3. The `.env.local` file is already created with the correct API proxy configuration.

4. Start the development server:
   ```bash
   npm run dev
   ```

5. The frontend will be available at: `http://localhost:5173`

## Step 5: Access the Application

1. Open your browser and go to: `http://localhost:5173`
2. Login with the default admin credentials:
   - Email: `admin@gmail.com`
   - Password: `admin123`

## Troubleshooting

### API not responding
- Check that WAMP services are running
- Verify the database exists and has the correct schema
- Check `api/config.php` for correct database credentials
- Check Apache error logs in WAMP

### CORS errors
- The Vite proxy should handle CORS automatically
- If issues persist, check `api/util.php` for CORS headers

### Database connection errors
- Verify MySQL is running in WAMP
- Check database credentials in `api/config.php`
- Default WAMP MySQL user is usually `root` with an empty password

### Frontend not connecting to API
- Ensure the Vite dev server is running on port 5173
- Check that the proxy configuration in `vite.config.js` is correct
- Verify the API is accessible at `http://localhost/highway/api/`

## Project Structure

```
highway/
├── api/              # PHP REST API (accessible via WAMP)
│   ├── index.php     # Main API router
│   ├── config.php    # Configuration
│   └── .htaccess     # Apache rewrite rules
├── app/              # Vue.js + Ionic frontend
│   ├── src/          # Source files
│   ├── vite.config.js # Vite configuration with API proxy
│   └── .env.local    # Environment variables
└── setup-database.sql # Database schema
```

## Development Workflow

1. **Backend (PHP API)**: Runs automatically via WAMP at `http://localhost/highway/api/`
2. **Frontend (Vue.js)**: Run `npm run dev` in the `app` directory
3. **Database**: Managed via phpMyAdmin or MySQL command line

## Next Steps

- Configure Firebase if you need real-time sensor/SOS features
- Update JWT secret in production
- Set up proper environment variables for different environments

 
