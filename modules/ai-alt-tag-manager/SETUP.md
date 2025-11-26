# SETUP INSTRUCTIONS - AI Alt Tag Manager

## 🎯 You're Almost Ready!

The module is installed at:
`wp-content/plugins/onyx-command/modules/ai-alt-tag-manager/`

## ⚡ Quick Setup (2 minutes)

### Step 1: Get Your Claude API Key
1. Go to https://console.anthropic.com
2. Sign in (or create account)
3. Click "Get API Keys"
4. Create a new key
5. Copy it!

### Step 2: Add Your API Key
Open: `ai-alt-tag-manager.php`
Find line 17:
```php
private $api_key = 'YOUR_CLAUDE_API_KEY_HERE';
```

Replace with:
```php
private $api_key = 'sk-ant-your-actual-key-here';
```

### Step 3: Activate in Onyx Command
1. WordPress Admin → Onyx Command
2. Find "AI Alt Tag Manager"
3. Toggle it ON
4. Done!

## ✅ Testing

1. Upload a test image
2. Check if it gets an alt tag automatically
3. Go to Media → Missing Alt Tags
4. Try the bulk generate button

## 🎊 Features You'll Have

✓ Auto-generate on upload
✓ Bulk process all images
✓ AI button on media pages
✓ Manual editing
✓ Beautiful UI

Need help? The module logs everything to WordPress debug.log
