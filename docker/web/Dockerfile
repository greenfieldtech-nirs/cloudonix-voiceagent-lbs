# Development stage
FROM node:20-alpine AS development

# Set working directory
WORKDIR /app

# Copy package files
COPY frontend/package*.json ./

# Install dependencies (including dev dependencies for Tailwind)
RUN npm install

# Copy source code
COPY frontend/ ./

# Expose port
EXPOSE 3000

# Start development server
CMD ["npm", "start"]

# Production build stage
FROM development AS build

# Build the application
RUN npm run build

# Production stage
FROM nginx:alpine AS production

# Copy built application
COPY --from=build /app/build /usr/share/nginx/html

# Copy nginx configuration
COPY docker/web/nginx.conf /etc/nginx/conf.d/default.conf

# Expose port
EXPOSE 80

# Start nginx
CMD ["nginx", "-g", "daemon off;"]