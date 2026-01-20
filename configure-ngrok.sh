#!/bin/bash

#!/bin/bash

# ngrok Configuration Script for Cloudonix Voice Agent Load Balancer
# This script configures ngrok for webhook development

set -e

echo "📡 Cloudonix Voice Agent Load Balancer - ngrok Setup"
echo "===================================================="
echo ""
echo "⚠️  IMPORTANT: You need to configure ngrok with your auth token"
echo ""
echo "1. Get your auth token from: https://dashboard.ngrok.com/get-started/your-authtoken"
echo "2. Run the following command:"
echo "   ngrok config add-authtoken YOUR_AUTH_TOKEN_HERE"
echo ""
echo "3. Then start the tunnel:"
echo "   ngrok http 80"
echo ""
echo "4. Copy the HTTPS URL and use it for Cloudonix webhook configuration"
echo "   Example: https://abc123.ngrok.io/api/voice/application/your-domain"
echo ""
echo "💡 The tunnel will expose your local nginx on port 80 to the internet"
echo "💡 Use the HTTPS URL for all webhook endpoints in Cloudonix"