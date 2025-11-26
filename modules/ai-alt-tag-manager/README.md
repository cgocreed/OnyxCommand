# AI Alt Tag Manager Module

## Overview
Automatically generates SEO-friendly alt tags for images using Claude AI.

## Features
- ✅ Automatic generation on image upload
- ✅ Bulk processing for images without alt tags
- ✅ AI suggestion button on media edit pages  
- ✅ Manual editing capability
- ✅ Real-time progress tracking

## Setup
1. **Get your Claude API key** from https://console.anthropic.com
2. **Edit the module file**: Open `ai-alt-tag-manager.php`
3. **Find line 17**: `private $api_key = 'YOUR_CLAUDE_API_KEY_HERE';`
4. **Replace** with your actual API key
5. **Upload to Onyx Command**: Go to Onyx Command → Upload Module
6. **Activate** the module

## Usage

### Automatic Generation
Upload any image - alt tag is generated automatically!

### Bulk Processing
1. Go to Media → Missing Alt Tags
2. Click "Generate All Alt Tags Automatically"
3. Wait for processing to complete

### Manual Editing
1. Media → Missing Alt Tags
2. Click "Generate AI Alt Tag" for individual images
3. Edit the text if needed
4. Click "Save Alt Tag"

### On Media Edit Page
1. Edit any image
2. Scroll to "AI Alt Tag Suggestion"
3. Click "Get AI Suggestion"
4. Copy the suggestion to the alt tag field

## Files
- `ai-alt-tag-manager.php` - Main module file
- `assets/style.css` - Styling
- `assets/script.js` - JavaScript functionality

## Requirements
- WordPress 5.0+
- Onyx Command plugin
- Claude API key
- PHP 7.4+

## Author
Callum Creed
