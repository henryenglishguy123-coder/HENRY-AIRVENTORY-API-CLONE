/**
 * Global JWT Authentication Helper
 * Provides utilities for JWT token management
 */

/**
 * Get JWT Token from Cookie
 * @param {string} name - Cookie name (defaults to 'jwt_token')
 * @returns {string|null} JWT token or null if not found
 */
function getJWTToken(name = 'jwt_token') {
    if (typeof document === 'undefined') {
        return null;
    }

    const value = "; " + document.cookie;
    const parts = value.split("; " + name + "=");

    if (parts.length === 2) {
        const token = parts.pop().split(";").shift();
        return token ? token.trim() : null;
    }

    return null;
}

/**
 * Get Authorization Header with JWT Token
 * @param {string} cookieName - Cookie name (defaults to 'jwt_token')
 * @returns {object} Headers object with Authorization header if token exists
 */
function getAuthHeaders(cookieName = 'jwt_token') {
    const token = getJWTToken(cookieName);
    const headers = {
        'Accept': 'application/json'
    };

    if (token) {
        headers['Authorization'] = `Bearer ${token}`;
    }

    return headers;
}

/**
 * Make Authenticated API Request
 * @param {string} url - API endpoint URL
 * @param {object} options - Fetch options
 * @param {string} tokenCookie - JWT token cookie name
 * @returns {Promise} Fetch promise
 */
function authenticatedFetch(url, options = {}, tokenCookie = 'jwt_token') {
    const token = getJWTToken(tokenCookie);

    if (!token) {
        console.warn('JWT Token not found in cookies');
    }

    const headers = {
        ...getAuthHeaders(tokenCookie),
        ...options.headers
    };

    // Only add Content-Type for requests with JSON body
    const isFormData = options.body instanceof FormData;
    const hasContentType = options.headers && options.headers['Content-Type'];
    const isBodylessMethod = options.method && ['GET', 'HEAD'].includes(options.method.toUpperCase());

    if (!isFormData && !hasContentType && !isBodylessMethod && options.body) {
        headers['Content-Type'] = 'application/json';
    }

    return fetch(url, {
        ...options,
        headers
    });
}

// Export for use in modules if needed
if (typeof module !== 'undefined' && module.exports) {
    module.exports = {
        getJWTToken,
        getAuthHeaders,
        authenticatedFetch
    };
}
