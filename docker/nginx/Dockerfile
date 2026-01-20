FROM nginx:alpine

# Copy nginx configuration
COPY docker/nginx/default.conf /etc/nginx/conf.d/default.conf

# Remove default nginx configuration
RUN rm -f /etc/nginx/conf.d/default.conf

# Copy our configuration as default
COPY docker/nginx/default.conf /etc/nginx/conf.d/default.conf

# Create log directory
RUN mkdir -p /var/log/nginx

# Expose port 80
EXPOSE 80

# Start nginx
CMD ["nginx", "-g", "daemon off;"]