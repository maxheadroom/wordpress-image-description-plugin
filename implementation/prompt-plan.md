# WordPress Image Description Plugin - Implementation Plan (MVP)

## Checklist
- [x] Prompt 1: Set up basic plugin structure and activation
- [ ] Prompt 2: Create settings page with API configuration
- [ ] Prompt 3: Implement OpenAI API client
- [ ] Prompt 4: Add bulk action to Media Library
- [ ] Prompt 5: Create basic batch processing system
- [ ] Prompt 6: Implement image description generation
- [ ] Prompt 7: Add test mode preview page
- [ ] Prompt 8: Implement production mode (apply descriptions)
- [ ] Prompt 9: Add basic error handling and user feedback
- [ ] Prompt 10: Wire everything together and test complete workflow

## Implementation Prompts

### Prompt 1: Set up basic plugin structure and activation
Create the foundational WordPress plugin structure with proper headers, activation/deactivation hooks, and basic file organization. Set up the main plugin file and core class structure.

1. Create the main plugin file `wp-image-descriptions.php` with proper WordPress plugin headers
2. Implement plugin activation and deactivation hooks
3. Create the core plugin class `PluginCore` in `includes/class-plugin-core.php`
4. Set up basic autoloading or include structure for plugin classes
5. Add basic security checks (direct access prevention)
6. Create placeholder methods for initialization and hook registration

Focus on WordPress plugin best practices and ensure the plugin can be activated without errors. Don't implement any functionality yet, just establish the foundation.

### Prompt 2: Create settings page with API configuration
Implement the WordPress Settings API to create a dedicated settings page under the WordPress Settings menu. Include fields for OpenAI API configuration and basic plugin settings.

1. Create `Settings` class in `includes/class-settings.php`
2. Register settings page under WordPress Settings menu
3. Implement settings fields for:
   - API endpoint URL
   - API key (password field)
   - Model selection (dropdown)
   - Max tokens (number input)
   - Default prompt template (textarea)
4. Add settings validation and sanitization
5. Include capability checks (only administrators can access)
6. Wire the settings class into the main plugin initialization

Ensure settings are properly saved to the WordPress options table and can be retrieved throughout the plugin.

### Prompt 3: Implement OpenAI API client
Create the API client class that handles communication with OpenAI-compatible APIs. Include basic error handling and response processing for image description generation.

1. Create `ApiClient` class in `includes/class-api-client.php`
2. Implement method to send image description requests to OpenAI API
3. Handle image encoding (base64) for API transmission
4. Process API responses and extract generated descriptions
5. Implement basic error handling for common API errors (401, 429, 500)
6. Add method to test API connection with current settings
7. Include proper HTTP request handling using WordPress HTTP API

Focus on core API communication functionality. Don't implement advanced retry logic yet, just basic error detection and reporting.

### Prompt 4: Add bulk action to Media Library
Integrate with WordPress Media Library to add a bulk action for generating image descriptions. Include basic filtering to show images with/without alt text.

1. Create `MediaLibrary` class in `includes/class-media-library.php`
2. Add bulk action "Generate Descriptions" to media library list table
3. Handle bulk action processing to capture selected attachment IDs
4. Add custom column to media library showing alt text status
5. Implement basic filtering to show images missing alt text
6. Add capability checks (editors and authors can use bulk action)
7. Redirect to processing page after bulk action selection

Ensure the bulk action appears in the media library and properly captures selected images for processing.

### Prompt 5: Create basic batch processing system
Implement a simple batch processing system to manage multiple image description jobs. Create database tables and basic job management.

1. Create `BatchManager` class in `includes/class-batch-manager.php`
2. Create database tables for batches and jobs on plugin activation
3. Implement methods to:
   - Create new batch with selected attachment IDs
   - Store batch metadata (user, mode, settings)
   - Create individual jobs for each image
   - Track batch progress and status
4. Add basic batch status tracking (pending, processing, completed)
5. Include database cleanup on plugin deactivation
6. Wire batch creation into the bulk action handler

Focus on data structure and basic CRUD operations. Don't implement background processing yet.

### Prompt 6: Implement image description generation
Create the core functionality that processes individual images and generates descriptions using the API client. Handle the actual description generation workflow.

1. Create `QueueProcessor` class in `includes/class-queue-processor.php`
2. Implement method to process a single image description job:
   - Retrieve image attachment data
   - Get image URL or file path
   - Call API client to generate description
   - Store result in job record
   - Update job status
3. Add basic error handling for individual job failures
4. Implement method to process entire batch sequentially
5. Include validation for image formats and accessibility
6. Add logging for successful and failed processing

Keep processing simple and synchronous for MVP. Focus on the core description generation workflow.

### Prompt 7: Add test mode preview page
Create a dedicated admin page that displays generated descriptions in test mode, allowing users to review results before applying them to the media library.

1. Create `PreviewPage` class in `includes/class-preview-page.php`
2. Add admin page accessible via query parameter from bulk action
3. Display batch results in a table format showing:
   - Image thumbnail
   - Original alt text (if any)
   - Generated description
   - Status (success/failed)
4. Include "Apply Descriptions" and "Cancel" action buttons
5. Add basic styling for readability
6. Handle batch result display and navigation
7. Wire preview page into the batch processing workflow

Focus on clear presentation of results and user decision points.

### Prompt 8: Implement production mode (apply descriptions)
Add functionality to apply generated descriptions to WordPress media library attachments. Handle both test mode confirmation and direct production mode processing.

1. Extend `BatchManager` to handle applying batch results
2. Implement method to update attachment alt text in WordPress:
   - Use `update_post_meta()` to set `_wp_attachment_image_alt`
   - Handle existing alt text (preserve or overwrite based on settings)
   - Update job status after successful application
3. Add production mode processing that skips preview
4. Include success/failure tracking for applied descriptions
5. Add user feedback messages for completed operations
6. Handle batch cleanup after successful application

Ensure descriptions are properly saved to WordPress and accessible to screen readers.

### Prompt 9: Add basic error handling and user feedback
Implement comprehensive error handling, user notifications, and basic retry mechanisms to make the plugin robust and user-friendly.

1. Enhance error handling across all classes:
   - API connection failures
   - Invalid image formats
   - Permission errors
   - Database errors
2. Add WordPress admin notices for:
   - Successful batch completion
   - Processing errors
   - Configuration issues
3. Implement basic retry logic for failed API calls
4. Add error logging to WordPress error log
5. Include user-friendly error messages
6. Add validation for plugin settings and user inputs

Focus on graceful error handling and clear communication to users about what went wrong and how to fix it.

### Prompt 10: Wire everything together and test complete workflow
Integrate all components into a cohesive plugin, add final touches, and ensure the complete user workflow functions properly from start to finish.

1. Complete plugin initialization in `PluginCore`:
   - Load all classes
   - Register all hooks
   - Initialize components in proper order
2. Test complete user workflows:
   - Settings configuration
   - Bulk action selection
   - Test mode processing and preview
   - Production mode application
   - Error scenarios
3. Add any missing integration points between components
4. Include basic plugin documentation (README)
5. Add uninstall cleanup functionality
6. Perform final testing and bug fixes

Ensure the plugin works as a complete, integrated solution that users can install and use immediately.

## MVP Scope Notes

This implementation plan focuses on core functionality:
- ✅ Basic settings and API configuration
- ✅ Media library bulk action integration
- ✅ Simple batch processing (synchronous)
- ✅ Test mode with preview page
- ✅ Production mode with direct application
- ✅ Essential error handling

**Excluded from MVP** (can be added later):
- Advanced queue processing with Action Scheduler
- Real-time progress indicators
- Batch cancellation during processing
- Advanced retry strategies
- Custom prompt template management UI
- Detailed analytics and reporting
- Multi-language support

The MVP will provide a fully functional plugin that users can immediately use to generate and apply image descriptions, with the foundation to add advanced features in future iterations.
