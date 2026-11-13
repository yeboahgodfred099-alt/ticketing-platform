#!/bin/sh
# ==============================================================================
# ClevaWave Ticketing Platform — Production Deployment Automation
# Target: VPS running Docker / Docker Compose
# ==============================================================================

set -eu

echo "=========================================================="
echo "🚀 ClevaWave Ticketing Platform — VPS Deployment Initiated"
echo "=========================================================="

# Check if .env file exists
if [ ! -f .env ]; then
    echo "❌ ERROR: .env file not found in current directory."
    echo "Please create a .env file using .env.production.example as a template."
    exit 1
fi

echo "📦 1. Building and starting production Docker containers..."
docker compose -f docker-compose.prod.yml build --pull
docker compose -f docker-compose.prod.yml up -d

echo "⏳ 2. Waiting for database and backend readiness..."
sleep 5

echo "🗄️ 3. Running database migrations..."
docker compose -f docker-compose.prod.yml exec -T backend php artisan migrate --force

echo "🌱 4. Ensuring roles and permissions are seeded..."
docker compose -f docker-compose.prod.yml exec -T backend php artisan db:seed --class=RolePermissionSeeder --force

echo "⚡ 5. Warming up application caches..."
docker compose -f docker-compose.prod.yml exec -T backend php artisan config:cache
docker compose -f docker-compose.prod.yml exec -T backend php artisan route:cache
docker compose -f docker-compose.prod.yml exec -T backend php artisan view:cache

echo "🔄 6. Restarting background queue workers..."
docker compose -f docker-compose.prod.yml exec -T backend php artisan queue:restart

echo "🏥 7. Checking system health..."
sleep 2
docker compose -f docker-compose.prod.yml exec -T backend php artisan route:list > /dev/null

echo "=========================================================="
echo "✅ Deployment completed successfully!"
echo "Next step: If this is the initial deployment, provision the Super Admin:"
echo "  docker compose -f docker-compose.prod.yml exec backend php artisan admin:create-super"
echo "=========================================================="
