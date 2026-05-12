# VRide Database Troubleshooting Guide

## Issue: Website Not Working on Railway

Your deployed website at `https://web-production-0b7c35.up.railway.app/` is not working because the **database is not properly configured**.

---

## ✅ What I Fixed

1. **Updated `db.php`** - Now supports Railway's `DATABASE_URL` environment variable
2. **Enhanced diagnostics** - Added `diagnose.php` to check database status
3. **Improved health check** - `health_check.php` now shows detailed info

---

## 🚀 RAILWAY SETUP STEPS

### Step 1: Add MySQL Database Plugin to Railway

1. Go to https://railway.app/dashboard
2. Click on your project: `vride-production`
3. Click **New → Database → MySQL**
4. Wait for it to finish provisioning (1-2 minutes)
5. ✅ Railway will automatically set the `DATABASE_URL` environment variable

### Step 2: Verify Railway Has MySQL

1. Go to **Deployments → Environment Variables**
2. Look for a variable named `DATABASE_URL` that looks like:
   ```
   mysql://railway:xxxxxxxxxxxx@containers.railway.app:7159/railway
   ```
3. If you don't see it, **you don't have MySQL added yet** - Go back to Step 1

### Step 3: Redeploy Your App

1. Go to **Deployments**
2. Click **Deploy** or wait for auto-deploy if connected to GitHub
3. Wait for deployment to complete

### Step 4: Test Database Connection

**Access the diagnostic page:**
```
https://web-production-0b7c35.up.railway.app/diagnose.php
```

You should see JSON output like:
```json
{
  "status": "success",
  "database": {
    "connected": true
  },
  "tables": {
    "users": {"exists": true, "records": 3},
    "vehicles": {"exists": true, "records": 10},
    "bookings": {"exists": true, "records": 0}
  }
}
```

---

## 🔍 Troubleshooting

### Problem: `"connected": false`

**Cause:** Railway MySQL is not provisioned

**Solution:**
1. Go to Railway Dashboard
2. Check if MySQL plugin exists (look for green checkmark)
3. If not, add it: **New → Database → MySQL**
4. Wait 2 minutes for provisioning
5. Redeploy your app

### Problem: Tables Show `"exists": false`

**Cause:** Database connected but tables not created

**Solution:**
1. Access the health check:
   ```
   https://web-production-0b7c35.up.railway.app/health_check.php
   ```
2. It should show `INCOMPLETE - Need admin account` and auto-create tables
3. Refresh after 10 seconds
4. If still showing MISSING, check Railway MySQL logs

### Problem: `admin_accounts` is 0

**Cause:** Admin user not created

**Solution:**
Access:
```
https://web-production-0b7c35.up.railway.app/health_check.php
```
It will auto-create a default admin:
- **Email:** `admin@vrental.com`
- **Password:** `admin123`

---

## 📊 Check Status Pages

| URL | Purpose |
|-----|---------|
| `/diagnose.php` | JSON diagnostic data (for debugging) |
| `/health_check.php` | Plain text health report |
| `/login.php` | Admin login (default: `admin@vrental.com` / `admin123`) |

---

## 🔧 Local Testing (Before Deployment)

To test locally, ensure MySQL is running:

```bash
# Check health locally
php health_check.php

# Output should show:
# Configuration:
# - Host: localhost
# - Database: vehicle_rental
# 
# DB: OK ✓
# Tables: ...
```

---

## ⚡ Quick Summary

| Step | Action |
|------|--------|
| 1️⃣ | Go to Railway Dashboard |
| 2️⃣ | Add MySQL database plugin |
| 3️⃣ | Wait for `DATABASE_URL` environment variable |
| 4️⃣ | Redeploy your app |
| 5️⃣ | Visit `/diagnose.php` to verify |

---

## 📞 Still Not Working?

1. Check the **Rails/Railway Logs** section for errors
2. Verify MySQL plugin shows **"running"** status (green)
3. Copy the full error message from `/diagnose.php` and investigate

---

**Note:** The database initialization is now automatic! When any page loads and `db.php` is included, it will:
- Create missing tables
- Add missing columns
- Create default admin account (if none exists)

