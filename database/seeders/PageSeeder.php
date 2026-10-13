<?php

namespace Database\Seeders;

use App\Models\Media;
use App\Models\Page;
use App\Models\PageCategory;
use Database\Seeders\Demo\DemoMedia;
use Database\Seeders\Demo\DemoPages;
use Illuminate\Database\Seeder;

class PageSeeder extends Seeder
{
    public function run(): void
    {
        // Skip if demo pages already seeded (check for a specific demo page, not count)
        if (Page::withTrashed()->where('slug', 'about')->exists()) {
            $this->command?->info('Pages already exist. Skipping.');

            return;
        }

        $categories = PageCategory::pluck('id', 'slug');

        // =====================================================================
        // 1. HOMEPAGE — Rich landing page (ALL containers full_width)
        // =====================================================================
        // Delete the simple installer homepage if it exists, replace with rich demo version
        // Use forceDelete to physically remove the row so the unique slug constraint is freed
        Page::withTrashed()->where('slug', '/')->forceDelete();

        $home = Page::create([
            'title' => 'Home',
            'slug' => '/',
            'content_type' => 'blocks',
            'blocks' => DemoPages::for('/'),
            'is_root' => true,
            'is_active' => true,
            'level' => 0,
            'order' => 0,
            'template' => 'full_width',
            'meta_title' => 'Welcome | Inverys CMS',
            'meta_description' => 'Inverys CMS — a modern, extensible content management system with drag-and-drop page builder, swappable themes, plugin architecture, and REST API.',
            'meta_robots' => 'index, follow',
            'include_in_search' => true,
        ]);
        if ($categories->has('general')) {
            $home->categories()->attach($categories['general']);
        }

        // =====================================================================
        // 2. ABOUT — Team, history, values (mix of full_width and constrained)
        // =====================================================================
        $about = Page::create([
            'title' => 'About Us',
            'slug' => 'about',
            'content_type' => 'blocks',
            'blocks' => DemoPages::for('about'),
            'template' => 'full_width',
            'is_active' => true,
            'level' => 0,
            'order' => 1,
            'meta_title' => 'About Us | Inverys CMS',
            'meta_description' => 'Learn about the team, values, and history behind Inverys CMS.',
            'meta_robots' => 'index, follow',
            'include_in_search' => true,
        ]);
        if ($categories->has('general')) {
            $about->categories()->attach($categories['general']);
        }

        // =====================================================================
        // 3. SERVICES (parent) — mix of full_width and constrained
        // =====================================================================
        $services = Page::create([
            'title' => 'Services',
            'slug' => 'services',
            'content_type' => 'blocks',
            'blocks' => DemoPages::for('services'),
            'template' => 'full_width',
            'is_active' => true,
            'level' => 0,
            'order' => 2,
            'meta_title' => 'Services | Inverys CMS',
            'meta_description' => 'Discover our web development, consulting, and CMS implementation services.',
            'meta_robots' => 'index, follow',
            'include_in_search' => true,
        ]);
        if ($categories->has('general')) {
            $services->categories()->attach($categories['general']);
        }

        // =====================================================================
        // 3a. WEB DEVELOPMENT (child of Services) — mostly constrained
        // =====================================================================
        Page::create([
            'title' => 'Web Development',
            'slug' => 'web-development',
            'content_type' => 'blocks',
            'blocks' => DemoPages::for('web-development'),
            'parent_id' => $services->id,
            'is_active' => true,
            'level' => 1,
            'order' => 0,
            'meta_title' => 'Web Development | Inverys CMS',
            'meta_description' => 'Custom web development services — Laravel, Vue.js, API development, and more.',
            'include_in_search' => true,
        ]);

        // =====================================================================
        // 3b. CONSULTING (child of Services) — mostly constrained
        // =====================================================================
        Page::create([
            'title' => 'Consulting',
            'slug' => 'consulting',
            'content_type' => 'blocks',
            'blocks' => DemoPages::for('consulting'),
            'parent_id' => $services->id,
            'is_active' => true,
            'level' => 1,
            'order' => 1,
            'meta_title' => 'Consulting | Inverys CMS',
            'meta_description' => 'Expert technology consulting — architecture reviews, performance audits, and security assessments.',
            'include_in_search' => true,
        ]);

        // =====================================================================
        // 4. CONTACT — mix of full_width and constrained
        // =====================================================================
        Page::create([
            'title' => 'Contact',
            'slug' => 'contact',
            'content_type' => 'blocks',
            'blocks' => DemoPages::for('contact'),
            'template' => 'full_width',
            'is_active' => true,
            'level' => 0,
            'order' => 3,
            'meta_title' => 'Contact | Inverys CMS',
            'meta_description' => 'Get in touch with the Inverys team. Berlin, Germany.',
            'meta_robots' => 'index, follow',
            'include_in_search' => true,
        ]);

        // =====================================================================
        // 5. PRIVACY POLICY
        // =====================================================================
        $privacy = Page::create([
            'title' => 'Privacy Policy',
            'slug' => 'privacy-policy',
            'content_type' => 'html',
            'content' => '<p><strong>Last updated:</strong> March 19, 2026</p>
<p>Inverys GmbH ("Inverys", "we", "us", or "our") operates the Inverys CMS platform. This Privacy Policy explains how we collect, use, disclose, and safeguard your information when you use our software and services.</p>

<h3>1. Information We Collect</h3>
<h4>1.1 Information You Provide</h4>
<p>We collect information you provide directly to us, including:</p>
<ul>
<li><strong>Account Information:</strong> When you create an account, we collect your name, email address, and password.</li>
<li><strong>Profile Information:</strong> Any additional information you add to your profile, such as a profile picture, biography, or job title.</li>
<li><strong>Content:</strong> Pages, media files, and other content you create through the CMS.</li>
<li><strong>Communications:</strong> Information you provide when you contact us for support or feedback.</li>
</ul>

<h4>1.2 Information Collected Automatically</h4>
<p>When you use our platform, we automatically collect:</p>
<ul>
<li><strong>Log Data:</strong> IP address, browser type, operating system, referring URLs, pages viewed, and timestamps.</li>
<li><strong>Device Information:</strong> Hardware model, operating system version, and unique device identifiers.</li>
<li><strong>Usage Data:</strong> Features used, actions taken, and performance metrics.</li>
<li><strong>Cookies:</strong> Session cookies for authentication and preference cookies for your settings. See Section 5 for details.</li>
</ul>

<h3>2. How We Use Your Information</h3>
<p>We use the information we collect to:</p>
<ul>
<li>Provide, maintain, and improve our services</li>
<li>Authenticate your identity and manage your account</li>
<li>Process transactions and send related information</li>
<li>Send technical notices, updates, security alerts, and support messages</li>
<li>Respond to your comments, questions, and requests</li>
<li>Monitor and analyze trends, usage, and activities</li>
<li>Detect, investigate, and prevent fraud and other illegal activities</li>
<li>Comply with legal obligations</li>
</ul>

<h3>3. Data Sharing and Disclosure</h3>
<p>We do not sell your personal data. We may share information in the following circumstances:</p>
<ul>
<li><strong>Service Providers:</strong> With third-party vendors who perform services on our behalf (hosting, analytics, email delivery).</li>
<li><strong>Legal Requirements:</strong> When required by law, regulation, or legal process.</li>
<li><strong>Protection:</strong> To protect the rights, property, or safety of Inverys, our users, or the public.</li>
<li><strong>Business Transfers:</strong> In connection with a merger, acquisition, or sale of assets.</li>
</ul>

<h3>4. Data Security</h3>
<p>We implement appropriate technical and organizational measures to protect your personal data, including:</p>
<ul>
<li>Encryption of data in transit (TLS 1.3) and at rest (AES-256)</li>
<li>Regular security audits and penetration testing</li>
<li>Access controls and role-based permissions</li>
<li>Automated backup and disaster recovery procedures</li>
<li>Employee security training and data handling policies</li>
</ul>

<h3>5. Cookies</h3>
<p>We use the following types of cookies:</p>
<ul>
<li><strong>Essential Cookies:</strong> Required for authentication and core functionality. Cannot be disabled.</li>
<li><strong>Preference Cookies:</strong> Remember your settings such as language, theme, and sidebar state.</li>
<li><strong>Analytics Cookies:</strong> Help us understand how users interact with our platform. Can be disabled in your account settings.</li>
</ul>

<h3>6. Your Rights (GDPR)</h3>
<p>Under the General Data Protection Regulation, you have the right to:</p>
<ul>
<li><strong>Access:</strong> Request a copy of your personal data</li>
<li><strong>Rectification:</strong> Request correction of inaccurate data</li>
<li><strong>Erasure:</strong> Request deletion of your data ("right to be forgotten")</li>
<li><strong>Restriction:</strong> Request limitation of processing</li>
<li><strong>Portability:</strong> Receive your data in a structured, machine-readable format</li>
<li><strong>Objection:</strong> Object to processing based on legitimate interests</li>
</ul>
<p>To exercise these rights, contact us at <strong>privacy@inverys.com</strong>. We will respond within 30 days.</p>

<h3>7. Data Retention</h3>
<p>We retain your personal data for as long as your account is active or as needed to provide services. Upon account deletion, we remove your personal data within 30 days, except where retention is required by law or for legitimate business purposes (such as fraud prevention).</p>

<h3>8. International Data Transfers</h3>
<p>Your data is processed and stored within the European Union. If data transfer outside the EU is necessary, we ensure appropriate safeguards are in place, including Standard Contractual Clauses approved by the European Commission.</p>

<h3>9. Children\'s Privacy</h3>
<p>Our services are not intended for children under 16. We do not knowingly collect personal data from children. If you believe a child has provided us with personal data, please contact us immediately.</p>

<h3>10. Changes to This Policy</h3>
<p>We may update this Privacy Policy from time to time. We will notify you of material changes by posting the updated policy on this page and updating the "Last updated" date. Your continued use of our services after changes constitutes acceptance of the updated policy.</p>

<h3>11. Contact</h3>
<p>For privacy-related inquiries:<br>
<strong>Inverys GmbH</strong><br>
Friedrichstra&szlig;e 123, 10117 Berlin, Germany<br>
Email: <strong>privacy@inverys.com</strong><br>
Data Protection Officer: <strong>dpo@inverys.com</strong></p>',
            'is_active' => true,
            'hide_in_menu' => true,
            'level' => 0,
            'order' => 4,
            'nav_title' => 'Privacy',
            'meta_title' => 'Privacy Policy | Inverys CMS',
            'meta_description' => 'Privacy policy for Inverys CMS — how we collect, use, and protect your data.',
            'meta_robots' => 'noindex, follow',
            'include_in_search' => false,
        ]);
        if ($categories->has('legal')) {
            $privacy->categories()->attach($categories['legal']);
        }

        // =====================================================================
        // 6. TERMS OF SERVICE
        // =====================================================================
        $terms = Page::create([
            'title' => 'Terms of Service',
            'slug' => 'terms-of-service',
            'content_type' => 'html',
            'content' => '<p><strong>Last updated:</strong> March 19, 2026</p>
<p>These Terms of Service ("Terms") govern your access to and use of the Inverys CMS platform, including any associated services, features, and content (collectively, the "Service"), provided by Inverys GmbH ("Inverys", "we", "us").</p>

<h3>1. Acceptance of Terms</h3>
<p>By accessing or using the Service, you agree to be bound by these Terms. If you do not agree to these Terms, you may not access or use the Service. If you are using the Service on behalf of an organization, you represent that you have the authority to bind that organization to these Terms.</p>

<h3>2. Account Registration</h3>
<p>To use certain features of the Service, you must create an account. You agree to:</p>
<ul>
<li>Provide accurate, current, and complete registration information</li>
<li>Maintain the security of your password and account</li>
<li>Promptly update your information if it changes</li>
<li>Accept responsibility for all activities that occur under your account</li>
<li>Notify us immediately of any unauthorized access or use</li>
</ul>

<h3>3. License and Use Rights</h3>
<h4>3.1 Open Source License</h4>
<p>Inverys CMS is released under the MIT License. You may use, copy, modify, merge, publish, distribute, sublicense, and/or sell copies of the software, subject to the conditions of the MIT License.</p>
<h4>3.2 Your Content</h4>
<p>You retain ownership of all content you create, upload, or publish through the Service. By using the Service, you grant us a limited license to host, store, and display your content solely for the purpose of providing the Service.</p>
<h4>3.3 Themes and Plugins</h4>
<p>Themes and plugins you create are your own intellectual property. You may distribute them freely, commercially, or under any license you choose.</p>

<h3>4. Acceptable Use</h3>
<p>You agree not to use the Service to:</p>
<ul>
<li>Violate any applicable laws or regulations</li>
<li>Infringe upon the intellectual property rights of others</li>
<li>Distribute malware, viruses, or other harmful code</li>
<li>Attempt to gain unauthorized access to other systems or accounts</li>
<li>Engage in any activity that disrupts or interferes with the Service</li>
<li>Harvest or collect personal information of other users without consent</li>
<li>Use the Service for spamming, phishing, or other fraudulent purposes</li>
</ul>

<h3>5. API Usage</h3>
<p>Access to the Inverys CMS API is subject to rate limits and fair usage policies. You agree to:</p>
<ul>
<li>Use API tokens responsibly and keep them confidential</li>
<li>Respect rate limits (default: 60 requests per minute per token)</li>
<li>Not circumvent or attempt to bypass rate limiting mechanisms</li>
<li>Include proper attribution when displaying content from the API</li>
</ul>

<h3>6. Service Availability</h3>
<p>We strive to maintain high availability but do not guarantee uninterrupted access. The Service may be temporarily unavailable due to maintenance, updates, or circumstances beyond our control. We will provide reasonable notice of planned maintenance when possible.</p>

<h3>7. Limitation of Liability</h3>
<p>To the maximum extent permitted by applicable law, Inverys shall not be liable for any indirect, incidental, special, consequential, or punitive damages, including loss of profits, data, or business opportunities, arising out of or related to your use of the Service.</p>

<h3>8. Indemnification</h3>
<p>You agree to indemnify and hold harmless Inverys and its officers, directors, employees, and agents from any claims, damages, or expenses arising from your use of the Service or violation of these Terms.</p>

<h3>9. Termination</h3>
<p>We may terminate or suspend your access to the Service at any time, with or without cause, upon notice. Upon termination, your right to use the Service ceases immediately. Provisions that by their nature should survive termination will survive, including ownership, warranty disclaimers, and limitations of liability.</p>

<h3>10. Governing Law</h3>
<p>These Terms are governed by and construed in accordance with the laws of the Federal Republic of Germany, without regard to conflict of law principles. Any disputes shall be subject to the exclusive jurisdiction of the courts in Berlin, Germany.</p>

<h3>11. Changes to Terms</h3>
<p>We reserve the right to modify these Terms at any time. Material changes will be communicated via email or through a prominent notice on the Service. Your continued use of the Service after changes constitutes acceptance.</p>

<h3>12. Contact</h3>
<p>For questions about these Terms:<br>
<strong>Inverys GmbH</strong><br>
Friedrichstra&szlig;e 123, 10117 Berlin, Germany<br>
Email: <strong>legal@inverys.com</strong></p>',
            'is_active' => true,
            'hide_in_menu' => true,
            'level' => 0,
            'order' => 5,
            'nav_title' => 'Terms',
            'meta_title' => 'Terms of Service | Inverys CMS',
            'meta_description' => 'Terms of service for Inverys CMS.',
            'meta_robots' => 'noindex, follow',
            'include_in_search' => false,
        ]);
        if ($categories->has('legal')) {
            $terms->categories()->attach($categories['legal']);
        }

        // =====================================================================
        // 7. FAQ — Comprehensive, categorized (mix of full_width and constrained)
        // =====================================================================
        $faq = Page::create([
            'title' => 'FAQ',
            'slug' => 'faq',
            'content_type' => 'blocks',
            'blocks' => DemoPages::for('faq'),
            'template' => 'full_width',
            'is_active' => true,
            'level' => 0,
            'order' => 6,
            'subtitle' => 'Find answers to common questions about Inverys CMS',
            'meta_title' => 'FAQ | Inverys CMS',
            'meta_description' => 'Frequently asked questions about Inverys CMS — general, page builder, themes, plugins, API, and security.',
            'meta_robots' => 'index, follow',
            'include_in_search' => true,
        ]);
        if ($categories->has('support')) {
            $faq->categories()->attach($categories['support']);
        }

        // =====================================================================
        // 8. FEATURES — Page Builder Showcase (ALL containers full_width)
        // =====================================================================
        Page::create([
            'title' => 'Features',
            'slug' => 'features',
            'content_type' => 'blocks',
            'blocks' => DemoPages::for('features'),
            'template' => 'full_width',
            'is_active' => true,
            'level' => 0,
            'order' => 7,
            'subtitle' => 'Everything Inverys CMS can do — each feature on its own page',
            'meta_title' => 'Features | Inverys CMS',
            'meta_description' => 'Page builder, themes, multilingual content, media library, users and security, plugins and API — the features of Inverys CMS.',
            'meta_robots' => 'index, follow',
            'include_in_search' => true,
        ]);
        if ($categories->has('landing')) {
            Page::where('slug', 'features')->first()?->categories()->attach($categories['landing']);
        }

        // =====================================================================
        // 9. DOCUMENTATION — mix of full_width and constrained
        // =====================================================================
        Page::create([
            'title' => 'Documentation',
            'slug' => 'documentation',
            'content_type' => 'blocks',
            'blocks' => DemoPages::for('documentation'),
            'template' => 'full_width',
            'is_active' => true,
            'level' => 0,
            'order' => 8,
            'subtitle' => 'Guides, references, and tutorials for Inverys CMS',
            'meta_title' => 'Documentation | Inverys CMS',
            'meta_description' => 'Complete documentation for Inverys CMS — installation, page builder, themes, plugins, and API reference.',
            'meta_robots' => 'index, follow',
            'include_in_search' => true,
        ]);
        if ($categories->has('support')) {
            Page::where('slug', 'documentation')->first()?->categories()->attach($categories['support']);
        }

        // =====================================================================
        // 10. External link demo (GitHub)
        // =====================================================================
        Page::create([
            'title' => 'GitHub',
            'slug' => 'github',
            'content_type' => 'html',
            'content' => '',
            'is_active' => true,
            'level' => 0,
            'order' => 9,
            'nav_title' => 'GitHub',
            'external_url' => 'https://github.com',
            'external_url_new_window' => true,
            'hide_in_menu' => false,
            'include_in_search' => false,
        ]);

        // Child pages, e.g. the individual features under "Features"
        DemoPages::createMissingChildren();

        // Write the illustrations to disk and create them in the media library
        DemoMedia::ensure();

        $this->command?->info('Demo pages seeded successfully! ('.Page::count().' pages, '.Media::count().' media)');
    }
}
