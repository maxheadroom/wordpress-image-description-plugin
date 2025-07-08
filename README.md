# WordPress Image Descriptions Plugin

An AI-powered WordPress plugin that automatically generates accessibility-focused alt text descriptions for images using OpenAI-compatible APIs.

## Features

### Core Functionality
- **Bulk Processing**: Generate descriptions for multiple images at once
- **Test Mode**: Preview and edit AI-generated descriptions before applying
- **Production Mode**: Apply descriptions directly without preview
- **Custom Prompts**: Customize the AI prompt template for your needs
- **Multiple AI Providers**: Support for OpenAI, Anthropic Claude, and compatible APIs

### User Experience
- **Media Library Integration**: Seamless bulk actions in WordPress Media Library
- **Progress Tracking**: Real-time progress updates during processing
- **Error Handling**: Comprehensive error messages and retry mechanisms
- **User Permissions**: Role-based access (admins configure, editors/authors use)
- **Accessibility Focus**: WCAG-compliant descriptions for screen readers

### Technical Features
- **Background Processing**: Non-blocking batch processing via WordPress cron
- **Rate Limiting**: Configurable delays to respect API limits
- **Retry Logic**: Automatic retry for failed API calls
- **Database Tracking**: Complete audit trail of all processing jobs
- **Security**: Proper capability checks, nonce verification, input sanitization

## Installation

1. **Download** the latest plugin ZIP file
2. **Upload** via WordPress Admin → Plugins → Add New → Upload Plugin
3. **Activate** the plugin
4. **Configure** your API settings in Settings → Image Descriptions

## Configuration

### API Settings
1. Go to **Settings → Image Descriptions**
2. Configure your API provider:
   - **Endpoint**: API URL (e.g., `https://api.openai.com/v1/chat/completions`)
   - **API Key**: Your authentication key
   - **Model**: AI model to use (e.g., `gpt-4-vision-preview`, `claude-3-sonnet-20240229`)
   - **Max Tokens**: Maximum response length (50-1000)
   - **Temperature**: Creativity level (0.0-2.0)

### Processing Settings
- **Batch Size**: Number of images to process simultaneously (1-20)
- **Rate Limit Delay**: Delay between API calls in seconds (0-10)
- **Max Retries**: Maximum retry attempts for failed requests (0-10)

### Prompt Template
Customize the AI prompt used to generate descriptions. Default:
```
Describe this image for accessibility purposes. Focus on the main subject, important details, and any text visible in the image. Keep the description concise but informative.
```

## Usage

### Test Mode (Recommended)
1. Go to **Media → Library** (List View)
2. Select images using checkboxes
3. Choose **"Generate Descriptions (Test Mode)"** from bulk actions
4. Click **Apply**
5. Review generated descriptions on the preview page
6. Edit descriptions if needed
7. Click **"Apply Descriptions to Media Library"**

### Production Mode (Direct Application)
1. Go to **Media → Library** (List View)
2. Select images using checkboxes
3. Choose **"Generate Descriptions (Apply Directly)"** from bulk actions
4. Click **Apply**
5. Descriptions are generated and applied automatically

### Filtering Images
Use the dropdown filter to show:
- **All images**
- **Missing alt text** (images that need descriptions)
- **Has alt text** (images with existing descriptions)

## Supported AI Providers

### OpenAI
- **Models**: `gpt-4-vision-preview`, `gpt-4o`, `gpt-4o-mini`
- **Endpoint**: `https://api.openai.com/v1/chat/completions`
- **Authentication**: Bearer token

### Anthropic Claude
- **Models**: `claude-3-opus-20240229`, `claude-3-sonnet-20240229`, `claude-3-haiku-20240307`
- **Endpoint**: `https://api.anthropic.com/v1/messages`
- **Authentication**: API key header

### Custom/Local APIs
- Any OpenAI-compatible API endpoint
- Local models via Ollama or similar

## Supported Image Formats

- **JPEG** (.jpg, .jpeg)
- **PNG** (.png)
- **GIF** (.gif)
- **WebP** (.webp)
- **Maximum size**: 20MB per image

## Troubleshooting

### Common Issues

**Bulk actions not visible:**
- Ensure you're in List View (not Grid View) in Media Library
- Check user permissions (need `edit_posts` capability)

**"Failed to create processing batch":**
- Check database tables exist (Tools → Image Descriptions Debug)
- Verify plugin activation completed successfully

**API errors:**
- Verify API key is correct and has sufficient credits
- Check endpoint URL format
- Test connection in Settings → Image Descriptions

**Processing stuck:**
- Check WordPress error logs for detailed error messages
- Verify images are in supported formats and under 20MB
- Try processing fewer images at once

### Diagnostics
Go to **Tools → Image Descriptions Debug** for:
- Database table status
- WordPress environment information
- User capabilities check
- Hook registration verification
- Bulk actions filter testing

### Error Messages
The plugin provides detailed error messages for:
- API configuration issues
- Rate limiting problems
- Authentication failures
- Image format problems
- Database errors

## Database Tables

The plugin creates two custom tables:

### `wp_image_description_batches`
Stores batch information and progress tracking.

### `wp_image_description_jobs`
Stores individual image processing jobs and results.

## Security

- **Capability Checks**: All admin functions require appropriate WordPress capabilities
- **Nonce Verification**: All form submissions use WordPress nonces
- **Input Sanitization**: All user inputs are properly sanitized
- **Output Escaping**: All outputs are escaped for security
- **API Key Storage**: API keys stored in WordPress options (consider encryption for production)

## Performance

- **Background Processing**: Uses WordPress cron for non-blocking processing
- **Rate Limiting**: Configurable delays prevent API overload
- **Batch Processing**: Efficient handling of multiple images
- **Memory Management**: Optimized for large image processing
- **Database Optimization**: Proper indexing and query optimization

## Accessibility

Generated descriptions follow WCAG 2.1 guidelines:
- **Concise but informative** (typically under 125 characters)
- **Context-aware** based on image usage
- **Factual descriptions** without subjective interpretations
- **Text content inclusion** when visible in images
- **Action and emotion description** when relevant

## Development

### File Structure
```
wp-image-descriptions/
├── wp-image-descriptions.php          # Main plugin file
├── includes/
│   ├── class-plugin-core.php          # Core plugin class
│   ├── class-settings.php             # Settings management
│   ├── class-media-library.php        # Media library integration
│   ├── class-api-client.php           # API communication
│   ├── class-batch-manager.php        # Batch processing
│   ├── class-queue-processor.php      # Job processing
│   ├── class-preview-page.php         # Preview interface
│   └── class-diagnostics.php          # Debugging tools
└── README.md                          # This file
```

### Hooks and Filters
- `wp_image_descriptions_process_batch` - Process batch in test mode
- `wp_image_descriptions_process_batch_production` - Process batch in production mode
- `bulk_actions-upload` - Add bulk actions to media library
- `handle_bulk_actions-upload` - Handle bulk action processing

## Support

For issues, feature requests, or contributions:
1. Check the diagnostics page for common issues
2. Review WordPress error logs for detailed error information
3. Verify API configuration and test connection
4. Try with default WordPress theme and minimal plugins

## License

GPL v2 or later

## Changelog

### Version 1.0.0
- Initial release
- Complete MVP functionality
- Test and production modes
- Comprehensive error handling
- Multi-provider API support
- Background processing
- Accessibility-focused descriptions
