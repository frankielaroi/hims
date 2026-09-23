#!/usr/bin/env bash
#
# One-time provisioning of the HIMS stack on Azure:
#   App Service (Linux, PHP 8.3) with a SQLite database on persistent /home storage.
#
# SQLite over App Service's network-mounted /home supports ONE instance only:
# the plan is pinned to a single worker and must not be scaled out.
#
# Requires the Azure CLI and `az login`. Override any variable below via env:
#   RESOURCE_GROUP=hims-prod APP_NAME=hims-korlebu bash .azure/provision.sh
#
# Creates billable resources. Safe to re-run: existing resources are updated.

set -euo pipefail

RESOURCE_GROUP="${RESOURCE_GROUP:-hims-rg}"
LOCATION="${LOCATION:-southafricanorth}"
PLAN_NAME="${PLAN_NAME:-hims-plan}"
PLAN_SKU="${PLAN_SKU:-B2}"
APP_NAME="${APP_NAME:?Set APP_NAME to a globally unique web app name}"
DB_PATH="${DB_PATH:-/home/data/hims-database/database.sqlite}"
APP_KEY="${APP_KEY:-base64:$(openssl rand -base64 32)}"

echo "==> Resource group"
az group create --name "$RESOURCE_GROUP" --location "$LOCATION" --output none

echo "==> App Service plan ($PLAN_SKU, Linux)"
az appservice plan create \
    --resource-group "$RESOURCE_GROUP" --name "$PLAN_NAME" \
    --is-linux --sku "$PLAN_SKU" --number-of-workers 1 --output none

echo "==> Web app"
az webapp create \
    --resource-group "$RESOURCE_GROUP" --plan "$PLAN_NAME" --name "$APP_NAME" \
    --runtime "PHP:8.3" --output none

APP_URL="https://$(az webapp show -g "$RESOURCE_GROUP" -n "$APP_NAME" --query defaultHostName -o tsv)"

echo "==> App settings"
az webapp config appsettings set \
    --resource-group "$RESOURCE_GROUP" --name "$APP_NAME" --output none --settings \
    APP_NAME="HIMS" \
    APP_ENV=production \
    APP_DEBUG=false \
    APP_KEY="$APP_KEY" \
    APP_URL="$APP_URL" \
    LOG_CHANNEL=stderr \
    LOG_LEVEL=warning \
    DB_CONNECTION=sqlite \
    DB_DATABASE="$DB_PATH" \
    DB_BUSY_TIMEOUT=10000 \
    SESSION_DRIVER=database \
    SESSION_LIFETIME=15 \
    SESSION_SECURE_COOKIE=true \
    CACHE_STORE=database \
    QUEUE_CONNECTION=database \
    FILESYSTEM_DISK=local \
    LARAVEL_STORAGE_PATH=/home/data/hims-storage \
    PHP_INI_SCAN_DIR="/usr/local/etc/php/conf.d:/home/site/wwwroot/.azure/php" \
    SCM_DO_BUILD_DURING_DEPLOYMENT=false

echo "==> Runtime config"
az webapp config set \
    --resource-group "$RESOURCE_GROUP" --name "$APP_NAME" --output none \
    --startup-file "bash /home/site/wwwroot/.azure/startup.sh" \
    --always-on true --http20-enabled true --min-tls-version 1.2 --ftps-state Disabled \
    --generic-configurations '{"healthCheckPath": "/up"}'

az webapp update \
    --resource-group "$RESOURCE_GROUP" --name "$APP_NAME" --https-only true --output none

az webapp log config \
    --resource-group "$RESOURCE_GROUP" --name "$APP_NAME" \
    --docker-container-logging filesystem --output none

echo
echo "Provisioned $APP_URL"
echo
echo "Next steps:"
echo "  1. Save APP_KEY somewhere safe (losing it breaks encrypted data): $APP_KEY"
echo "  2. Enable SCM basic auth publishing, then download the publish profile:"
echo "       az resource update -g $RESOURCE_GROUP --namespace Microsoft.Web --resource-type basicPublishingCredentialsPolicies --name scm --parent sites/$APP_NAME --set properties.allow=true"
echo "       az webapp deployment list-publishing-profiles -g $RESOURCE_GROUP -n $APP_NAME --xml"
echo "  3. In GitHub: add secret AZURE_WEBAPP_PUBLISH_PROFILE (the XML) and variable AZURE_WEBAPP_NAME=$APP_NAME"
echo "  4. Push to main (or run the workflow manually) to deploy."
