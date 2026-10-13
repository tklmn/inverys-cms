<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Installer Translation Lines
    |--------------------------------------------------------------------------
    |
    | Used by the pre-boot installer (public/index.php) and
    | the installation wizard (resources/views/install/wizard.blade.php).
    |
    */

    // Pre-boot (dependency installer)
    'setup_title' => 'Setup',
    'setup_subtitle' => 'Dependencies need to be installed before the CMS can start.',
    'setup_info' => 'This is a fresh installation. Click the button below to install PHP and frontend dependencies automatically. This requires <strong>Composer</strong> and optionally <strong>Node.js / npm</strong> on the server.',
    'setup_btn' => 'Install Dependencies',
    'setup_btn_loading' => 'Installing...',
    'setup_btn_retry' => 'Retry',
    'setup_step_composer' => 'Installing PHP packages (Composer)',
    'setup_step_npm' => 'Building frontend assets (npm)',
    'setup_err_composer' => 'Composer not found. Please install Composer or upload composer.phar to the project root.',
    'setup_err_composer_failed' => 'Composer install failed.',
    'setup_err_npm' => 'npm build failed.',
    'setup_warn_npm' => 'npm/Node.js not found on server. Frontend assets were not built. You can run "npm install && npm run build" manually or upload pre-built assets.',
    'setup_warn_npm_manual' => 'npm/Node.js not found on server. Frontend assets could not be built automatically.<br><br>Please run the following commands on your local machine and re-upload:<br><code style="display:block;margin-top:.5rem;padding:.5rem .75rem;background:rgba(0,0,0,.3);border-radius:6px;">npm install && npm run build</code>',
    'setup_subtitle_assets' => 'Frontend assets need to be built before the CMS can start.',
    'setup_info_assets' => 'PHP dependencies are already installed, but the frontend assets are missing. Click the button below to build them automatically. This requires <strong>Node.js / npm</strong> on the server.',
    'setup_btn_assets' => 'Build Frontend Assets',
    'setup_err_network' => 'Request failed',

    // Wizard — general
    'title' => 'Installation Wizard',
    'subtitle' => 'Set up your CMS in a few steps',

    // Wizard — Step 1: Requirements
    'requirements' => 'System Requirements',
    'requirements_desc' => 'Checking that your server meets all requirements.',
    'php_extensions' => 'PHP Extensions',
    'dir_permissions' => 'Directory Permissions',
    'writable' => 'Writable',
    'not_writable' => 'Not writable',
    'extension' => ':name extension',
    'extension_gd' => 'GD extension (thumbnails)',
    'loaded' => 'Loaded',
    'missing' => 'Missing',
    'env_file' => '.env file',

    // Wizard — Step 2: Database
    'database' => 'Database Configuration',
    'database_desc' => 'Choose your database driver and enter connection details.',
    'db_driver' => 'Database Driver',
    'sqlite_label' => 'SQLite (easiest — no config needed)',
    'host' => 'Host',
    'port' => 'Port',
    'username' => 'Username',
    'password' => 'Password',
    'connect_btn' => 'Connect & Load Databases',
    'test_btn' => 'Test Connection',
    'testing' => 'Testing...',
    'connecting' => 'Connecting...',
    'connected' => 'Connected!',
    'conn_failed' => 'Connection failed',
    'select_db' => 'Database',
    'select_db_placeholder' => '— Select a database —',
    'select_db_connect_first' => '-- Connect to server first --',
    'no_empty_db' => 'No empty databases found',
    'no_empty_db_msg' => 'Connected — but no empty databases found. Please create an empty database on your server first.',
    'db_hint' => 'Only empty databases (no tables) are shown. Create a new database on your server if none appear.',
    'select_db_error' => 'Please select a database before continuing.',

    // Wizard — Step 3: Settings
    'settings' => 'Site Settings',
    'settings_desc' => 'Configure your site name, URL, and language.',
    'site_name' => 'Site Name',
    'site_url' => 'Site URL',
    'site_url_hint' => 'The full URL where your site will be accessible (no trailing slash).',
    'language' => 'Language',
    'demo_content' => 'Demo Content',
    'demo_desc' => 'Core system (roles, permissions, blog) is always installed. Choose additional demo content below.',
    'seed_pages' => 'Sample pages',
    'seed_pages_desc' => 'Home, About, Contact, Services and more',
    'seed_media' => 'Demo media',
    'seed_media_desc' => 'SVG images and assets for the media library',
    'seed_categories' => 'Page categories',
    'seed_categories_desc' => 'General, Blog, Documentation, etc.',
    'seed_blog' => 'Blog demo posts',
    'seed_blog_desc' => 'Sample blog articles with featured images',

    // Wizard — Step 4: Admin
    'admin' => 'Admin Account',
    'admin_desc' => 'Create the first administrator account.',
    'first_name' => 'First Name',
    'last_name' => 'Last Name',
    'username' => 'Username',
    'email' => 'Email Address',
    'password_label' => 'Password',
    'password_confirm' => 'Confirm Password',
    'password_placeholder' => 'Min. 10 characters',
    'password_confirm_placeholder' => 'Repeat password',
    'err_required' => 'All fields are required.',
    'err_email' => 'Please enter a valid email address.',
    'err_password_short' => 'At least 10 characters with upper and lower case letters, a number and a special character.',
    'err_password_match' => 'Passwords do not match.',

    // Wizard — Step 5: Installing
    'installing' => 'Installing Inverys CMS',
    'installing_desc' => 'Please wait while we set everything up...',
    'all_done' => 'All done!',
    'paused' => 'Installation paused.',
    'paused_resume' => 'Installation paused — click Retry to continue.',
    'retry' => 'Retry',
    'complete_title' => 'Installation Complete!',
    'complete_desc' => 'Your Inverys CMS is ready. Log in with the admin account you just created.',
    'go_login' => 'Go to Login',

    // Wizard — Navigation
    'back' => '← Back',
    'next' => 'Next →',
    'install_now' => 'Install Now',
    'err_save' => 'Could not save configuration.',
    'err_generic' => 'An error occurred.',
    'err_network' => 'Network error',
    'err_plugin' => 'The system plugin ":slug" could not be activated. Details are in storage/logs/laravel.log.',

    // Default homepage content (database/seeders/StarterContent.php)
    'home_title' => 'Home',
    'home_eyebrow' => 'Your new website',
    'home_heading' => 'Welcome to :name',
    'home_text' => 'Your website is ready. Edit this page in the admin panel to add your own content.',
    'home_cta' => 'Open the admin panel',
    'home_cta_blog' => 'Visit the blog',
    'home_welcome_eyebrow' => 'Getting started',
    'home_welcome_title' => 'Your next steps',
    'home_welcome_text' => 'Log in to the admin panel to customize your site, create new pages, manage media, and configure your theme.',
    'home_step_pages_title' => 'Build your pages',
    'home_step_pages_text' => 'Create pages with the visual page builder. Blocks, backgrounds and icons are chosen from lists, no code needed.',
    'home_step_design_title' => 'Adjust the design',
    'home_step_design_text' => 'Set your logo, colours and font in the theme settings and edit the footer with the same blocks as your pages.',
    'home_step_blog_title' => 'Write your first post',
    'home_step_blog_text' => 'Share news and articles in the blog. Posts can be scheduled, sorted into categories and found through the site search.',

    // Default footer (installations without sample pages)
    'footer_about' => 'Thank you for visiting. More content is coming soon.',
    'footer_links' => 'Quick Links',
    'footer_blog' => 'Blog',
    'footer_copyright' => '© :year :name. All rights reserved.',

    // Step labels (install progress)
    'step_env' => 'Writing environment configuration',
    'step_key' => 'Generating application key',
    'step_database' => 'Setting up database',
    'step_pages' => 'Installing sample pages',
    'step_media' => 'Installing demo media',
    'step_categories' => 'Installing page categories',
    'step_blog' => 'Installing blog demo content',
    'step_admin' => 'Creating administrator account',
    'step_storage' => 'Linking storage directory',
    'step_finalize' => 'Finalizing installation',
];
