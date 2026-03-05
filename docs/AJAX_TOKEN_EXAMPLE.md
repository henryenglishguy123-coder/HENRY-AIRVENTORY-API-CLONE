# AJAX Token Minting Example for Admin Panel

This example demonstrates how to call the `/api/v1/admin/mint-token` endpoint from your admin panel views using AJAX.

## Prerequisites

Make sure you have the CSRF token meta tag in your layout file (already included in `resources/views/admin/layouts/app.blade.php`):

```html
<meta name="csrf-token" content="{{ csrf_token() }}">
```

## Example 1: Using jQuery (Recommended for Blade views)

Add this JavaScript to your admin panel view:

```html
@section('content')
<div class="container">
    <button id="mint-token-btn" class="btn btn-primary">Generate API Token</button>
    <div id="token-display" style="display:none;">
        <h4>Your API Token:</h4>
        <pre id="token-value"></pre>
    </div>
</div>
@endsection

@section('scripts')
<script>
$(document).ready(function() {
    $('#mint-token-btn').click(function() {
        const button = $(this);
        button.prop('disabled', true).text('Generating...');
        
        $.ajax({
            url: '/api/v1/admin/mint-token',
            type: 'POST',
            headers: {
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content'),
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            success: function(response) {
                if (response.success) {
                    // Store token in localStorage
                    localStorage.setItem('admin_token', response.token);
                    
                    // Display token (optional - for demo purposes)
                    $('#token-value').text(response.token);
                    $('#token-display').show();
                    
                    toastr.success('API token generated successfully!');
                    console.log('Token:', response.token);
                }
            },
            error: function(xhr) {
                console.error('Error:', xhr.responseJSON);
                const message = xhr.responseJSON?.message || 'Failed to generate token';
                toastr.error(message);
            },
            complete: function() {
                button.prop('disabled', false).text('Generate API Token');
            }
        });
    });
});
</script>
@endsection
```

## Example 2: Using Vanilla JavaScript (Fetch API)

```html
@section('content')
<div class="container">
    <button id="mint-token-btn" class="btn btn-primary">Generate API Token</button>
    <div id="token-display" style="display:none;">
        <h4>Your API Token:</h4>
        <pre id="token-value"></pre>
    </div>
</div>
@endsection

@section('scripts')
<script>
document.getElementById('mint-token-btn').addEventListener('click', async function() {
    const button = this;
    button.disabled = true;
    button.textContent = 'Generating...';
    
    try {
        const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
        
        const response = await fetch('/api/v1/admin/mint-token', {
            method: 'POST',
            headers: {
                'Accept': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
                'X-Requested-With': 'XMLHttpRequest'
            },
            credentials: 'include' // Important: include session cookie
        });
        
        const data = await response.json();
        
        if (data.success) {
            // Store token in localStorage
            localStorage.setItem('admin_token', data.token);
            
            // Display token (optional - for demo purposes)
            document.getElementById('token-value').textContent = data.token;
            document.getElementById('token-display').style.display = 'block';
            
            alert('API token generated successfully!');
            console.log('Token:', data.token);
        } else {
            throw new Error(data.message || 'Failed to generate token');
        }
    } catch (error) {
        console.error('Error:', error);
        alert('Error: ' + error.message);
    } finally {
        button.disabled = false;
        button.textContent = 'Generate API Token';
    }
});
</script>
@endsection
```

## Example 3: Using Axios

```html
@section('scripts')
<script src="https://cdn.jsdelivr.net/npm/axios/dist/axios.min.js"></script>
<script>
// Configure Axios defaults
axios.defaults.headers.common['X-CSRF-TOKEN'] = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';
axios.defaults.withCredentials = true;

document.getElementById('mint-token-btn').addEventListener('click', async function() {
    try {
        const response = await axios.post('/api/v1/admin/mint-token');
        
        if (response.data.success) {
            localStorage.setItem('admin_token', response.data.token);
            alert('Token generated: ' + response.data.token);
            console.log('Token:', response.data.token);
        }
    } catch (error) {
        console.error('Error:', error.response?.data);
        alert('Error: ' + (error.response?.data?.message || 'Failed to generate token'));
    }
});
</script>
@endsection
```

## Using the Token for API Calls

Once you have the token, use it for subsequent API requests:

```javascript
// Example: Get current admin info
const token = localStorage.getItem('admin_token');

fetch('/api/v1/admin/me', {
    headers: {
        'Authorization': 'Bearer ' + token,
        'Accept': 'application/json'
    }
})
.then(response => response.json())
.then(data => {
    console.log('Admin info:', data.admin);
})
.catch(error => {
    console.error('Error:', error);
});
```

## Important Notes

1. **Session Required**: You must be logged in via `/admin/login` before calling mint-token
2. **CSRF Token**: Always include the CSRF token in POST requests
3. **Credentials**: Use `credentials: 'include'` for fetch or `withCredentials: true` for axios
4. **X-Requested-With**: Include this header to help Laravel recognize AJAX requests
5. **Token Storage**: Store the token securely (localStorage for demo, consider more secure options for production)

## Troubleshooting

- **401 Unauthenticated**: Make sure you're logged in via the admin panel
- **419 CSRF Token Mismatch**: Verify CSRF token is correctly included
- **Session Expired**: Login again and retry
- **CORS Issues**: The endpoint is on the same domain, so CORS shouldn't be an issue
