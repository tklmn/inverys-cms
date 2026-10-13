<?php

declare(strict_types=1);

namespace Database\Seeders\Demo;

use App\Models\Page;
use Database\Seeders\Demo\Blocks as B;

/**
 * Blocks of the demo pages.
 *
 * One source for the PageSeeder and for refreshing existing
 * installations (php artisan cms:demo-refresh). Styling uses only the
 * page builder's options — any editor can rebuild what is here.
 *
 * The texts are English; the German version lives in
 * plugins/language-de/demo/de.json and is matched via the English text.
 */
final class DemoPages
{
    private const IMG = 'storage/media/demo/';

    /** Slug → method */
    public const PAGES = [
        '/' => 'home',
        'about' => 'about',
        'services' => 'services',
        'web-development' => 'webDevelopment',
        'consulting' => 'consulting',
        'contact' => 'contact',
        'faq' => 'faq',
        'features' => 'features',
        'page-builder' => 'pageBuilderFeature',
        'themes-and-design' => 'themesFeature',
        'multilingual' => 'multilingualFeature',
        'media-library' => 'mediaFeature',
        'users-and-security' => 'securityFeature',
        'for-developers' => 'developersFeature',
        'documentation' => 'documentation',
    ];

    /**
     * Demo child pages, per parent page: slug → page fields.
     * They appear in the menu as a submenu of the parent page.
     */
    public const CHILDREN = [
        'features' => [
            'page-builder' => ['title' => 'Page Builder', 'meta_description' => 'Design pages visually with 20+ blocks — backgrounds, alignment and icons chosen from lists, no code needed.'],
            'themes-and-design' => ['title' => 'Themes & Design', 'meta_description' => 'Colours, font, corners, header and footer in the theme customizer — in light and dark mode.'],
            'multilingual' => ['title' => 'Multilingual', 'meta_description' => 'Pages, posts, menus and settings in several languages, each with its own URL and fallback to the original.'],
            'media-library' => ['title' => 'Media Library', 'meta_description' => 'Upload once, use everywhere — the central media library with thumbnails, search and alt texts.'],
            'users-and-security' => ['title' => 'Users & Security', 'meta_description' => 'Roles, permissions, two-factor login, revisions and an activity log.'],
            'for-developers' => ['title' => 'For Developers', 'meta_description' => 'Plugins, a REST API for headless setups and a clean Laravel codebase.'],
        ],
    ];

    /**
     * Create missing child pages (CHILDREN) under existing parent pages.
     *
     * @return int number of newly created pages
     */
    public static function createMissingChildren(): int
    {
        $created = 0;

        foreach (self::CHILDREN as $parentSlug => $children) {
            $parent = Page::where('slug', $parentSlug)->first();
            if (! $parent) {
                continue;
            }

            $order = 0;
            foreach ($children as $slug => $fields) {
                if (! Page::withTrashed()->where('slug', $slug)->exists()) {
                    Page::create($fields + [
                        'slug' => $slug,
                        'content_type' => 'blocks',
                        'blocks' => self::for($slug),
                        'template' => 'full_width',
                        'parent_id' => $parent->id,
                        'level' => $parent->level + 1,
                        'order' => $order,
                        'is_active' => true,
                        'meta_title' => $fields['title'].' | Inverys CMS',
                        'meta_robots' => 'index, follow',
                        'include_in_search' => true,
                    ]);
                    $created++;
                }
                $order++;
            }
        }

        return $created;
    }

    /** @return list<array>|null */
    public static function for(string $slug): ?array
    {
        $method = self::PAGES[$slug] ?? null;

        return $method ? self::$method() : null;
    }

    public static function home(): array
    {
        B::page('hp');

        return [
            B::hero([
                'eyebrow' => 'Open source · Built on Laravel',
                'title' => 'Build Beautiful Websites — Without Limits',
                'text' => 'A visual page builder, swappable themes, plugins and a full REST API. Everything you need to create, manage and publish professional websites.',
                'primary_text' => 'Get Started',
                'primary_url' => '/documentation',
                'secondary_text' => 'View Features',
                'secondary_url' => '/features',
                'image' => self::IMG.'hero-abstract.svg',
                'image_alt' => 'Illustration of the visual page builder',
            ], ['size' => 'large']),

            B::band('', [
                B::text('<p>Trusted by teams of every size</p>', 'small', 'center'),
                B::logos([
                    [self::IMG.'logo-1.svg', 'Circle logo'],
                    [self::IMG.'logo-2.svg', 'Peak logo'],
                    [self::IMG.'logo-3.svg', 'Grid logo'],
                    [self::IMG.'logo-4.svg', 'Orbit logo'],
                    [self::IMG.'logo-5.svg', 'Arc logo'],
                ]),
                B::spacer('2rem'),
                B::stats([
                    ['20+', 'Block types'],
                    ['100%', 'Open source (MIT)'],
                    ['2 min', 'From install to first page'],
                    ['REST', 'API for headless setups'],
                ]),
            ], 'py-12'),

            B::band('soft', [
                B::intro('Features', 'Everything You Need', 'A complete toolkit for building modern websites. From visual editing to developer APIs, Inverys CMS has you covered.'),
                B::cards([
                    ['icon' => 'layout-dashboard', 'title' => 'Visual Page Builder', 'content' => 'Drag and drop 20+ block types to create rich layouts. Choose backgrounds, alignment and icons from lists — no code required.', 'url' => '/page-builder', 'link_text' => 'Explore the page builder'],
                    ['icon' => 'palette', 'title' => 'Theme Engine', 'content' => 'Swap themes instantly with zero downtime. Each theme has its own settings, color schemes, and content regions. Build custom themes with Blade templates and JSON config.'],
                    ['icon' => 'puzzle', 'title' => 'Plugin Architecture', 'content' => 'Extend functionality with plugins. Event hooks, service providers, and a clean API make it easy to add features without touching core code.'],
                ]),
                B::cards([
                    ['icon' => 'image', 'title' => 'Media Library', 'content' => 'Upload, organize, and embed images from a central library. Automatic thumbnails, alt text management, and an intuitive AJAX browser.'],
                    ['icon' => 'code', 'title' => 'REST API', 'content' => 'Full RESTful API with token authentication. Build headless frontends, mobile apps, or integrate with third-party services.'],
                    ['icon' => 'shield-check', 'title' => 'Role-Based Access', 'content' => 'Fine-grained permissions with admin, editor, and user roles plus roles of your own. Control who can create, edit, publish, and delete content across your site.'],
                ]),
            ], 'py-20'),

            B::band('', [
                B::intro('In three steps', 'How It Works', 'Get up and running in three simple steps.'),
                B::cards([
                    ['icon' => 'terminal', 'title' => '1. Install', 'content' => 'Clone the repository, install the dependencies and run the installer. You will have a fully working CMS in under two minutes.'],
                    ['icon' => 'blocks', 'title' => '2. Create', 'content' => 'Use the visual page builder to create pages. Drag blocks onto the canvas, configure settings, and see your content take shape in real time.'],
                    ['icon' => 'rocket', 'title' => '3. Publish', 'content' => 'Hit publish and your pages are live. Configure SEO metadata, set navigation order, assign categories, and manage visibility — all from one place.'],
                ], ['style' => 'tinted', 'align' => 'center', 'icon_style' => 'gradient']),
            ], 'py-20'),

            B::band('glow', [
                B::intro('Voices', 'What People Are Saying'),
                B::columns([
                    [B::quote('The page builder is incredibly intuitive. I built a complete landing page in 20 minutes without touching any code. The block variety is impressive.', 'Sarah Chen', 'Frontend Developer', self::IMG.'avatar-1.svg')],
                    [B::quote('Finally, a CMS that respects developers. Clean code, great API, and the theme system is exactly what I\'ve been looking for. No more fighting the framework.', 'Marcus Weber', 'Full-Stack Engineer', self::IMG.'avatar-2.svg')],
                    [B::quote('We migrated three client sites to Inverys CMS and the content editors love it. The admin UI is polished and the learning curve is minimal.', 'Emily Rodriguez', 'Agency Director', self::IMG.'avatar-3.svg')],
                ]),
            ], 'py-20'),

            B::cta(
                'Ready to Build Something Amazing?',
                'Join developers and content creators who trust Inverys CMS to power their websites. Open source, flexible, and built for the modern web.',
                'Explore Features', '/features',
                'Read the Docs', '/documentation',
            ),
        ];
    }

    public static function about(): array
    {
        B::page('ab');

        return [
            B::hero([
                'eyebrow' => 'About us',
                'title' => 'Our Story',
                'text' => 'Inverys CMS was born from a simple frustration: existing content management systems were either too rigid for developers or too complex for content editors. We set out to build something that works beautifully for both.',
                'primary_text' => 'Get in Touch',
                'primary_url' => '/contact',
                'secondary_text' => 'Our Services',
                'secondary_url' => '/services',
                'image' => self::IMG.'about-team.svg',
                'image_alt' => 'Team collaboration illustration',
            ]),

            B::band('', [
                B::columns([
                    [
                        B::heading('Why We Built Inverys CMS', 'h2', 'left', 'Mission'),
                        B::text('<p>Founded in 2024, our team combines deep expertise in web development, user experience design, and open-source community building. Every feature in Inverys CMS reflects our commitment to simplicity, performance, and flexibility.</p>', 'lead'),
                    ],
                    [B::quote('The best CMS is one that gets out of your way and lets you focus on what matters most — your content and your audience.', 'Inverys Team')],
                ], 'gap-12', 'center'),
                B::spacer('2rem'),
                B::stats([
                    ['2024', 'Founded'],
                    ['3', 'Core team members'],
                    ['20+', 'Block types'],
                    ['MIT', 'License'],
                ]),
            ], 'py-20'),

            B::band('soft', [
                B::intro('Values', 'Our Core Values', 'The principles that guide every decision we make.'),
                B::cards([
                    ['icon' => 'wand-sparkles', 'title' => 'Simplicity', 'content' => 'Every feature should be intuitive. If it needs a manual, we haven\'t finished designing it yet.'],
                    ['icon' => 'shield-check', 'title' => 'Security', 'content' => 'Security is not an afterthought. CSRF protection, XSS prevention, RBAC, and input sanitization are built into every layer.'],
                    ['icon' => 'gauge', 'title' => 'Performance', 'content' => 'Speed matters. Optimized queries, efficient caching, lazy loading, and minimal asset footprint keep things fast.'],
                    ['icon' => 'globe', 'title' => 'Openness', 'content' => 'Open source at heart. Transparent development, community contributions welcome, and no vendor lock-in.'],
                ], ['align' => 'center']),
            ], 'py-20'),

            B::band('', [
                B::intro('Team', 'Meet the Team'),
                B::cards([
                    ['image' => self::IMG.'team-lead.svg', 'title' => 'Alex Richter', 'content' => 'Project Lead & Architect. 15+ years building web platforms. Obsessed with clean architecture and developer experience.'],
                    ['image' => self::IMG.'team-design.svg', 'title' => 'Lena Vasquez', 'content' => 'UX & Design Lead. Believes that great software should feel invisible. Designs every interaction in the admin UI.'],
                    ['image' => self::IMG.'team-dev.svg', 'title' => 'Jonas Park', 'content' => 'Core Developer. Laravel specialist and API architect. Maintains the plugin system and REST endpoints.'],
                ], ['align' => 'center', 'image_style' => 'round'], 'gap-8'),
            ], 'py-20'),

            B::band('soft', [
                B::intro('Timeline', 'Our Journey'),
                B::cards([
                    ['icon' => 'lightbulb', 'title' => '2024 — The Beginning', 'content' => 'Project kickoff as a Laravel experiment. First working prototype with basic page management and a simple theme engine. The page builder concept takes shape with 6 initial block types.'],
                    ['icon' => 'layers', 'title' => '2025 — Core Architecture', 'content' => 'Complete rewrite with a focus on extensibility. Plugin architecture introduced. Theme system rebuilt with JSON settings, content regions, and color scheme support. Block count grows to 12.'],
                    ['icon' => 'rocket', 'title' => '2026 — Public Release', 'content' => 'First public release with 18+ block types, REST API, media library, and full documentation. The page builder supports drag-and-drop, inline settings, and real-time preview.'],
                    ['icon' => 'sparkles', 'title' => 'The Future', 'content' => 'Visual form builder, marketplace for themes and plugins, and collaborative editing. We\'re building the CMS we always wanted to use.'],
                ], ['style' => 'plain', 'icon_style' => 'gradient']),
            ], 'py-20'),

            B::cta(
                'Want to Join Us?',
                'We\'re always looking for contributors. Whether you write code, design interfaces, or create documentation — there\'s a place for you.',
                'Get in Touch', '/contact',
            ),
        ];
    }

    public static function services(): array
    {
        B::page('sv');

        return [
            B::hero([
                'eyebrow' => 'Services',
                'title' => 'What We Offer',
                'text' => 'From custom development to strategic consulting, CMS implementation to ongoing support — we deliver solutions that grow with your business.',
                'primary_text' => 'Start a Project',
                'primary_url' => '/contact',
                'secondary_text' => 'How We Work',
                'secondary_url' => '#how-we-work',
            ], ['layout' => 'centered']),

            B::band('', [
                B::cards([
                    ['image' => self::IMG.'services-web.svg', 'title' => 'Web Development', 'content' => 'Custom web applications tailored to your business needs. From responsive marketing sites to complex platforms, we build with modern tools and best practices.', 'url' => '/web-development', 'link_text' => 'Learn More'],
                    ['image' => self::IMG.'services-consulting.svg', 'title' => 'Technology Consulting', 'content' => 'Strategic technology consulting — architecture reviews, performance audits, security assessments, and actionable roadmaps to guide your technical decisions.', 'url' => '/consulting', 'link_text' => 'Learn More'],
                ], [], 'gap-8'),
            ], 'py-12'),

            B::band('soft', [
                B::intro('More', 'Additional Services', 'Beyond our core offerings, we provide specialized services to support every stage of your project.'),
                B::cards([
                    ['icon' => 'layers', 'title' => 'CMS Implementation', 'content' => 'Full Inverys CMS setup, custom theme development, content migration, and training for your team.'],
                    ['icon' => 'puzzle', 'title' => 'Plugin Development', 'content' => 'Custom plugins for your specific needs. We build, test, and maintain extensions that integrate seamlessly.'],
                    ['icon' => 'life-buoy', 'title' => 'Ongoing Support', 'content' => 'Dedicated support plans with SLA guarantees. Updates, monitoring, backups, and priority bug fixes.'],
                ]),
            ], 'py-20'),

            B::band('', [
                B::intro('Process', 'How We Work', 'A proven four-step process that keeps projects on track and delivers results.'),
                B::cards([
                    ['icon' => 'search', 'title' => 'Discovery', 'content' => 'We learn about your goals, audience, and technical requirements to define the project scope.'],
                    ['icon' => 'pen-tool', 'title' => 'Design', 'content' => 'Wireframes and prototypes to visualize the solution before writing any code.'],
                    ['icon' => 'code', 'title' => 'Develop', 'content' => 'Agile development with regular check-ins, testing at every stage, and transparent progress tracking.'],
                    ['icon' => 'rocket', 'title' => 'Launch', 'content' => 'Deployment, performance tuning, and handover with documentation and training.'],
                ], ['style' => 'tinted', 'align' => 'center', 'icon_style' => 'gradient']),
                B::spacer('1rem'),
                B::alert('Our Technology Stack', 'We specialize in Laravel, Vue.js, Alpine.js, Tailwind CSS, and PostgreSQL/MySQL. All projects follow PSR standards and include comprehensive test suites.'),
            ], 'py-20', 'max-w-7xl', '', 'how-we-work'),

            B::band('soft', [
                B::intro('Pricing', 'Transparent Packages', 'Fixed prices for typical projects — or a custom offer for everything else.'),
                B::pricing([
                    ['name' => 'Starter', 'price' => '€1,900', 'period' => 'one-off', 'description' => 'For a professional first website.',
                        'features' => ['Up to 5 pages with the page builder', 'Default theme in your colours', 'Contact form and imprint', 'Handover and training'],
                        'button_text' => 'Get in Touch', 'button_url' => '/contact'],
                    ['name' => 'Business', 'price' => '€4,900', 'period' => 'one-off', 'description' => 'For growing companies with more content.',
                        'features' => ['Up to 20 pages and a blog', 'Two languages', 'Custom theme adjustments', 'SEO setup and analytics', '3 months of support'],
                        'button_text' => 'Get in Touch', 'button_url' => '/contact', 'featured' => true, 'badge' => 'Most popular'],
                    ['name' => 'Enterprise', 'price' => 'On request', 'description' => 'For platforms, integrations and custom plugins.',
                        'features' => ['Custom plugins and integrations', 'Headless setups via the REST API', 'Service level agreement', 'Dedicated contact person'],
                        'button_text' => 'Request an Offer', 'button_url' => '/contact'],
                ]),
            ], 'py-20'),

            B::cta(
                'Ready to Start a Project?',
                'Tell us about your project and we\'ll get back to you within 24 hours with a free consultation and proposal.',
                'Get in Touch', '/contact',
            ),
        ];
    }

    public static function webDevelopment(): array
    {
        B::page('wd');

        return [
            B::text('<p>We build custom web applications tailored to your business needs. From simple marketing sites to complex web platforms, our team delivers high-quality, scalable solutions.</p>', 'lead'),
            B::spacer('1rem'),
            B::heading('Our Expertise', 'h2', 'left', 'What we do'),
            B::accordion([
                ['title' => 'Frontend Development', 'content' => 'Modern, responsive interfaces built with Vue.js, React, or Alpine.js. We follow component-driven architecture with comprehensive testing using Vitest and Cypress. Every interface is accessible (WCAG 2.1 AA) and performs well on all devices.'],
                ['title' => 'Backend Development', 'content' => 'Robust server-side solutions with Laravel, featuring clean architecture, comprehensive test suites (PHPUnit + Pest), queue workers for background processing, and efficient database design with optimized queries.'],
                ['title' => 'API Development', 'content' => 'RESTful and GraphQL APIs with token authentication, rate limiting, versioning, and comprehensive documentation. We build APIs that are a joy for other developers to consume.'],
                ['title' => 'Database Design', 'content' => 'Efficient schema design for MySQL, PostgreSQL, and SQLite. Proper indexing, migration strategies, seeders, and query optimization for high-traffic applications.'],
                ['title' => 'DevOps & Deployment', 'content' => 'CI/CD pipelines with GitHub Actions, Docker containerization, automated testing, staging environments, and zero-downtime deployments to AWS, DigitalOcean, or your preferred hosting.'],
            ]),
            B::spacer('2rem'),
            B::heading('Our Development Process', 'h2', 'left', 'Process'),
            B::columns([
                [B::card(['icon' => 'search', 'title' => '1. Discovery', 'content' => 'Requirements gathering, user research, and technical feasibility analysis.'], ['style' => 'tinted'])],
                [B::card(['icon' => 'pen-tool', 'title' => '2. Design', 'content' => 'Wireframes, prototypes, and UI design with your feedback at every step.'], ['style' => 'tinted'])],
                [B::card(['icon' => 'code', 'title' => '3. Build', 'content' => 'Agile development with weekly demos, code reviews, and automated testing.'], ['style' => 'tinted'])],
                [B::card(['icon' => 'rocket', 'title' => '4. Launch', 'content' => 'Deployment, monitoring, training, and ongoing support to ensure success.'], ['style' => 'tinted'])],
            ], 'gap-4'),
            B::spacer('2rem'),
            B::heading('Sample: Laravel Controller', 'h3'),
            B::code("<?php\n\nnamespace App\\Http\\Controllers\\Api;\n\nuse App\\Models\\Page;\nuse Illuminate\\Http\\JsonResponse;\n\nclass PageController extends Controller\n{\n    public function index(): JsonResponse\n    {\n        \$pages = Page::where('is_active', true)\n            ->with('categories')\n            ->orderBy('order')\n            ->paginate(15);\n\n        return response()->json(\$pages);\n    }\n}", 'php'),
            B::spacer('1rem'),
            B::alert('Free Consultation', 'Not sure where to start? Book a free 30-minute consultation to discuss your project requirements.', 'success'),
            B::buttons([
                ['Contact Us', '/contact', 'primary', 'arrow-right'],
                ['All Services', '/services', 'secondary'],
            ], '', 'lg'),
        ];
    }

    public static function consulting(): array
    {
        B::page('cn');

        return [
            B::text('<p>Make informed technology decisions with expert guidance. We analyze your infrastructure, identify opportunities, and create actionable roadmaps.</p>', 'lead'),
            B::spacer('1rem'),
            B::columns([
                [
                    B::heading('What We Assess', 'h2'),
                    B::list([
                        'Technology stack suitability and modernization paths',
                        'Application architecture and scalability bottlenecks',
                        'Database performance and query optimization',
                        'Security vulnerabilities and hardening measures',
                        'CI/CD pipeline efficiency and deployment practices',
                        'Code quality, test coverage, and maintainability',
                    ]),
                ],
                [
                    B::heading('What You Receive', 'h2'),
                    B::list([
                        'Comprehensive audit report with findings and severity levels',
                        'Prioritized recommendations with effort estimates',
                        'Architecture diagrams and improvement proposals',
                        'Implementation roadmap with milestones',
                        'Follow-up consultation to review progress',
                    ], 'ordered'),
                ],
            ], 'gap-8'),
            B::spacer('1rem'),
            B::stats([
                ['40+', 'Audits completed'],
                ['2 weeks', 'Typical assessment'],
                ['30 min', 'Free first call'],
            ], 'cards', 'center'),
            B::spacer('1rem'),
            B::alert('Engagement Options', 'We offer one-time assessments, ongoing advisory retainers, and hands-on implementation support. Each engagement is tailored to your specific needs and budget.'),
            B::quote('Their architecture review saved us months of refactoring work. The recommendations were practical, prioritized, and immediately actionable.', 'Thomas K.', 'CTO at DataFlow GmbH'),
            B::button('Schedule a Consultation', '/contact', 'primary', ['size' => 'lg'], 'calendar'),
        ];
    }

    public static function contact(): array
    {
        B::page('ct');

        return [
            B::hero([
                'eyebrow' => 'Contact',
                'title' => 'Get in Touch',
                'text' => 'Have a question, project idea, or want to work with us? We\'d love to hear from you. Reach out through any of the channels below and we\'ll get back to you promptly.',
                'primary_text' => 'Send us an Email',
                'primary_url' => 'mailto:hello@inverys.com',
                'secondary_text' => 'Call Us',
                'secondary_url' => 'tel:+493012345678',
                'image' => self::IMG.'contact-hero.svg',
                'image_alt' => 'Contact illustration',
            ]),

            B::band('', [
                B::cards([
                    ['icon' => 'mail', 'title' => 'Email', 'content' => 'hello@inverys.com', 'url' => 'mailto:hello@inverys.com', 'link_text' => 'Write to us'],
                    ['icon' => 'phone', 'title' => 'Phone', 'content' => '+49 30 123 456 78', 'url' => 'tel:+493012345678', 'link_text' => 'Call us'],
                    ['icon' => 'map-pin', 'title' => 'Address', 'content' => 'Friedrichstraße 123, 10117 Berlin, Germany'],
                ], ['style' => 'tinted', 'align' => 'center', 'icon_style' => 'gradient']),
            ], 'py-12'),

            B::band('soft', [
                B::columns([
                    [
                        B::heading('Contact Information', 'h2', 'left', 'Berlin'),
                        B::text('<p>We\'re based in Berlin, Germany, but work with clients worldwide. Don\'t hesitate to reach out — we\'re happy to discuss your project over a call or video chat.</p>'),
                        B::alert('Response Time', 'We typically respond within 24 business hours. For urgent matters, please call us directly.'),
                    ],
                    [B::image(self::IMG.'map-placeholder.svg', 'Map showing our office location in Berlin, Germany', 'Our office in the heart of Berlin')],
                ], 'gap-12', 'center'),
            ], 'py-20'),

            B::band('', [
                B::columns([
                    [
                        B::heading('Business Hours', 'h2'),
                        B::text('<p>Our team is available during the following hours (Central European Time):</p>'),
                        B::list([
                            'Monday - Friday: 9:00 AM - 6:00 PM CET',
                            'Saturday: 10:00 AM - 2:00 PM CET (support only)',
                            'Sunday: Closed',
                            'Public Holidays: Closed',
                        ]),
                        B::alert('Emergency Support', 'Clients on a support plan can reach us 24/7 via the dedicated support hotline.', 'warning'),
                    ],
                    [
                        B::heading('Connect With Us', 'h2'),
                        B::text('<p>Follow us on social media for updates, tips, and community highlights.</p>'),
                        B::links([
                            ['text' => 'GitHub — Source code & contributions', 'url' => 'https://github.com'],
                            ['text' => 'Twitter / X — News & updates', 'url' => 'https://x.com'],
                            ['text' => 'LinkedIn — Company profile', 'url' => 'https://linkedin.com'],
                            ['text' => 'Discord — Community chat', 'url' => 'https://discord.com'],
                            ['text' => 'YouTube — Tutorials & demos', 'url' => 'https://youtube.com'],
                        ]),
                    ],
                ], 'gap-12'),
            ], 'py-20'),
        ];
    }

    public static function faq(): array
    {
        B::page('fq');

        $group = fn (string $title, array $items) => [B::heading($title, 'h2'), B::accordion($items), B::spacer('1.5rem')];

        return [
            B::hero([
                'eyebrow' => 'Help',
                'title' => 'Frequently Asked Questions',
                'text' => 'Find answers to the most common questions about Inverys CMS. Can\'t find what you\'re looking for? Contact us and we\'ll be happy to help.',
                'primary_text' => 'Contact Us',
                'primary_url' => '/contact',
                'secondary_text' => 'Read the Docs',
                'secondary_url' => '/documentation',
            ], ['layout' => 'centered']),

            B::band('', [
                $group('General', [
                    ['title' => 'What is Inverys CMS?', 'content' => 'Inverys CMS is a modern, open-source content management system built with Laravel. It features a visual drag-and-drop page builder with 20+ block types, a swappable theme engine, a plugin architecture, media library, role-based access control, multilingual content, and a full REST API. It is designed for both content editors who want an intuitive interface and developers who want a clean, extensible codebase.'],
                    ['title' => 'Is Inverys CMS free to use?', 'content' => 'Yes. Inverys CMS is open source under the MIT License. You can use it for personal projects, commercial websites, client work, and SaaS products without any licensing fees. There are no premium tiers or feature gates.'],
                    ['title' => 'What are the system requirements?', 'content' => 'Inverys CMS requires PHP 8.4 or higher, Composer 2.x, Node.js 20+ (for building frontend assets), and a MySQL 8.0+, MariaDB, PostgreSQL 14+ or SQLite database. It runs on any standard LAMP/LEMP stack, Docker, Laravel Valet, or Laravel Herd.'],
                ]),
                $group('Page Builder', [
                    ['title' => 'What block types are available?', 'content' => 'The page builder includes 20+ block types: Hero, Heading, Text, Image, Video, Key Figures, Columns (2-6 col), Container, Section, Accordion, Tabs, Button, Link Group, Alert, Card, Quote, Code, List, Spacer, Divider, and Slider. Backgrounds, alignment, sizes and icons are chosen from lists — no CSS classes needed.'],
                    ['title' => 'Can I nest blocks inside other blocks?', 'content' => 'Yes. Container blocks like Columns, Sections, and Tabs accept child blocks. You can nest columns inside sections, place cards inside columns, or create complex layouts by combining containers. The drag-and-drop interface makes it easy to rearrange nested structures.'],
                    ['title' => 'Can I create custom block types?', 'content' => 'The block system is extensible by design. You can register new block types through the plugin system or by adding block views to your theme. Each block needs a type definition, a Blade template for rendering, and optionally a settings form for the editor.'],
                ]),
            ], 'py-20', 'max-w-4xl'),

            B::band('soft', [
                $group('Themes', [
                    ['title' => 'How do I create a custom theme?', 'content' => 'Create a directory in the themes/ folder with a theme.json manifest file. The manifest defines the theme name, version, author, settings schema, content regions, and color schemes. Add Blade templates in a views/ subdirectory. The minimum required templates are layouts/app.blade.php and page.blade.php. See the included "modern" theme as a reference.'],
                    ['title' => 'Can I switch themes without losing content?', 'content' => 'Absolutely. Themes control presentation only — your pages, blocks, media, and settings are stored independently. Switching themes is instant and non-destructive. Content regions may differ between themes, but your page content remains intact.'],
                    ['title' => 'Do themes support dark mode?', 'content' => 'Yes. The default theme ships with a light and a dark mode that follows the system preference, with a manual toggle. Colors, font, corners, header and footer style can be changed in the theme customizer.'],
                ]),
                $group('Plugins', [
                    ['title' => 'How do I install a plugin?', 'content' => 'Upload the plugin as a ZIP file under Extensions > Plugins, or place its directory in the plugins/ folder of your installation. Then enable it in the same list. Plugins are auto-discovered and can be activated or deactivated without restarting the application.'],
                    ['title' => 'Can plugins add new block types?', 'content' => 'Yes. Plugins can register new block types, add admin panel sections, define API endpoints, hook into events, add middleware, and extend the theme system. The plugin API provides service providers and event hooks for clean integration.'],
                ]),
            ], 'py-20', 'max-w-4xl'),

            B::band('', [
                $group('API', [
                    ['title' => 'Does Inverys CMS have an API?', 'content' => 'Yes. Inverys CMS provides a RESTful JSON API for pages, media, users, settings, themes and plugins; the blog adds posts and categories. Requests authenticate with API tokens (full, read-write or read-only), and lists support pagination and filters. You can use it to build headless frontends, mobile apps, or integrate with third-party services.'],
                    ['title' => 'What are the API rate limits?', 'content' => 'The default rate limit is 60 requests per minute per IP address. Administrators change it under Settings > Security. Rate limit headers (X-RateLimit-Limit, X-RateLimit-Remaining) are included in every response.'],
                    ['title' => 'Can I use the API for headless CMS setups?', 'content' => 'Absolutely. The API returns all page content including blocks in a structured JSON format. You can consume this data from any frontend framework — React, Vue, Svelte, Next.js, Nuxt, or even a mobile app. CORS settings are configurable.'],
                ]),
                $group('Security', [
                    ['title' => 'How does Inverys CMS handle security?', 'content' => 'Security is built into every layer. The CMS includes CSRF token protection on all forms, XSS prevention through Blade auto-escaping and content sanitization, SQL injection prevention through Eloquent ORM and parameterized queries, rate limiting on API endpoints, and bcrypt password hashing. All file uploads are validated and sanitized.'],
                    ['title' => 'Does it support role-based access control?', 'content' => 'Yes. Inverys CMS ships with three roles: Admin (full access), Editor (creates, edits and publishes all content, but does not manage users or the system) and User (a signed-in account without access to the admin area). Roles are enforced in the admin area and in the API. Roles of your own, with exactly the permissions they need, are created under Roles in the admin area.'],
                    ['title' => 'How are passwords stored?', 'content' => 'Passwords are hashed using bcrypt with a cost factor of 12 (configurable). Plain-text passwords are never stored or logged. The system supports password reset via email with time-limited, single-use tokens.'],
                ]),
            ], 'py-20', 'max-w-4xl'),

            B::cta(
                'Still Have Questions?',
                'Can\'t find the answer you\'re looking for? Our team is here to help.',
                'Contact Support', '/contact',
                'Read the Docs', '/documentation',
            ),
        ];
    }

    public static function features(): array
    {
        B::page('ftx');

        $feature = fn (string $icon, string $title, string $content, string $slug) => ['icon' => $icon, 'title' => $title, 'content' => $content, 'url' => '/'.$slug, 'link_text' => 'Learn more'];

        return [
            B::hero([
                'eyebrow' => 'Features',
                'title' => 'Everything a Modern Website Needs',
                'text' => 'From the visual page builder to the REST API — explore what Inverys CMS can do. Every feature has its own page with details and examples.',
                'primary_text' => 'Page Builder',
                'primary_url' => '/page-builder',
                'secondary_text' => 'Read the Docs',
                'secondary_url' => '/documentation',
                'image' => self::IMG.'features-overview.svg',
                'image_alt' => 'Features overview illustration showing block types',
            ], ['style' => 'dark']),

            B::band('', [
                B::stats([
                    ['20+', 'Block types'],
                    ['54', 'Icons for cards and buttons'],
                    ['2', 'Languages included'],
                    ['100%', 'Open source (MIT)'],
                ]),
            ], 'py-12'),

            B::band('soft', [
                B::intro('Overview', 'All Features at a Glance', 'Pick a topic to learn more. Each page shows what the feature does and how editors and developers work with it.'),
                B::cards([
                    $feature('layout-dashboard', 'Page Builder', 'Compose pages from 20+ blocks. Backgrounds, alignment and icons are chosen from lists — no code needed.', 'page-builder'),
                    $feature('palette', 'Themes & Design', 'Colours, font, corners, header and footer in the theme customizer — in light and dark mode.', 'themes-and-design'),
                    $feature('languages', 'Multilingual', 'Pages, posts, menus and settings in several languages, each with its own URL.', 'multilingual'),
                ], ['icon_style' => 'gradient']),
                B::cards([
                    $feature('image', 'Media Library', 'Upload, organise and reuse images across pages, posts and themes — with alt texts for accessibility.', 'media-library'),
                    $feature('shield-check', 'Users & Security', 'Roles, permissions, two-factor login, revisions and an activity log — secure by default.', 'users-and-security'),
                    $feature('code', 'For Developers', 'Plugins, a REST API for headless setups and a clean Laravel codebase.', 'for-developers'),
                ], ['icon_style' => 'gradient']),
            ], 'py-20'),

            B::cta(
                'Ready to Build Your Website?',
                'Install Inverys CMS in a few minutes and start with the demo content — or with an empty page.',
                'Get Started', '/documentation',
                'Contact Us', '/contact',
            ),
        ];
    }

    public static function pageBuilderFeature(): array
    {
        B::page('fpb');

        return [
            B::hero([
                'eyebrow' => 'Page builder',
                'title' => 'Design Pages Visually — Without Code',
                'text' => 'Drag blocks onto the page, fill in the content and choose the design from lists. What you see in the editor is what your visitors get.',
                'primary_text' => 'All Features',
                'primary_url' => '/features',
                'secondary_text' => 'Read the Docs',
                'secondary_url' => '/documentation',
                'image' => self::IMG.'blog-page-builder.svg',
                'image_alt' => 'Blocks of the page builder',
            ]),

            B::band('', [
                B::intro('How it works', 'Drag, Drop, Done'),
                B::cards([
                    ['icon' => 'blocks', 'title' => 'Drag and drop', 'content' => 'Add blocks from the palette, move them with the mouse and nest them in containers, columns, tabs and accordions.'],
                    ['icon' => 'palette', 'title' => 'Design by selection', 'content' => 'Background, alignment, size and icon are chosen from lists. No CSS classes, no Tailwind knowledge required.'],
                    ['icon' => 'monitor-smartphone', 'title' => 'Responsive by default', 'content' => 'Every block adapts to phones, tablets and large screens — columns stack automatically on small screens.'],
                ], ['style' => 'tinted', 'align' => 'center', 'icon_style' => 'gradient']),
            ], 'py-20'),

            B::band('soft', [
                B::intro('Blocks', 'More Than 20 Block Types'),
                B::columns([
                    [B::heading('Layout', 'h3'), B::list(['Hero (page intro)', 'Container and section', 'Columns (1–6)', 'Spacer and divider'])],
                    [B::heading('Content', 'h3'), B::list(['Heading and text', 'Image, video and slider', 'Key figures', 'Pricing table and logo strip', 'Quote, code and list'])],
                    [B::heading('Interactive', 'h3'), B::list(['Buttons and link group', 'Card with icon or image', 'Tabs and accordion', 'Alert'])],
                ], 'gap-8'),
            ], 'py-20'),

            B::band('', [
                B::columns([
                    [
                        B::heading('Nothing Gets Lost', 'h2', 'left', 'Safety net'),
                        B::text('<p>Changes are saved automatically while you work. Each editing session is kept as a revision, so earlier versions can be compared, previewed and restored at any time.</p>', 'lead'),
                        B::list(['Auto-save while editing', 'Revisions to compare, preview and restore', 'Undo, redo and keyboard shortcuts']),
                    ],
                    [B::image(self::IMG.'hero-abstract.svg', 'Illustration of the visual page builder')],
                ], 'gap-12', 'center'),
            ], 'py-20'),

            B::cta(
                'Every Page on This Website Was Built with the Page Builder',
                'No custom HTML. No template hacking. Just drag, drop, configure, and publish.',
                'All Features', '/features',
            ),
        ];
    }

    public static function themesFeature(): array
    {
        B::page('fth');

        return [
            B::hero([
                'eyebrow' => 'Themes & Design',
                'title' => 'Your Brand — in Light and Dark',
                'text' => 'Choose colours, font, corners, header and footer in the theme customizer. Every change works in light and dark mode — without touching a template.',
                'primary_text' => 'All Features',
                'primary_url' => '/features',
                'secondary_text' => 'Page Builder',
                'secondary_url' => '/page-builder',
                'image' => self::IMG.'blog-themes.svg',
                'image_alt' => 'Light and dark version of a page',
            ]),

            B::band('', [
                B::cards([
                    ['icon' => 'palette', 'title' => 'Theme customizer', 'content' => 'Primary and accent colour, font, corner style, content width, header and footer style — all set in the backend.'],
                    ['icon' => 'sparkles', 'title' => 'Light and dark mode', 'content' => 'Both modes are designed with care. Visitors follow their system setting or switch with one click.'],
                    ['icon' => 'layout-dashboard', 'title' => 'Content regions', 'content' => 'Areas such as the footer are edited with the same blocks as your pages — in every language.'],
                ], ['align' => 'center']),
            ], 'py-20'),

            B::band('soft', [
                B::columns([
                    [
                        B::heading('What You Can Set', 'h2', 'left', 'Customizer'),
                        B::list([
                            'Primary and accent colour',
                            'Dark mode background: midnight blue, black or slate',
                            'Font: Inter, Outfit, system font or serif headings',
                            'Corners: sharp, rounded or extra round',
                            'Header: translucent, solid or scrolling away',
                            'Footer: dark or light',
                            'Content width, page title, breadcrumbs and search',
                        ]),
                    ],
                    [
                        B::heading('For Theme Developers', 'h2', 'left', 'Blade & JSON'),
                        B::text('<p>A theme is a folder with Blade templates and a theme.json manifest. Settings declared there appear in the customizer automatically, and files in its assets folder are served with long browser caching — no build needed on the server.</p>'),
                        B::code("{\n    \"name\": \"My Theme\",\n    \"settings\": {\n        \"primary_color\": {\n            \"type\": \"color\",\n            \"label\": \"Primary Color\",\n            \"default\": \"#4f46e5\"\n        }\n    }\n}", 'json'),
                    ],
                ], 'gap-12'),
            ], 'py-20'),

            B::cta(
                'See the Theme in Both Modes',
                'Use the moon icon in the header to switch between light and dark mode.',
                'All Features', '/features',
            ),
        ];
    }

    public static function multilingualFeature(): array
    {
        B::page('fml');

        return [
            B::hero([
                'eyebrow' => 'Multilingual',
                'title' => 'One Website, Every Language',
                'text' => 'Translate pages, blog posts, menus and settings. Every language gets its own address, and anything not yet translated falls back to the original.',
                'primary_text' => 'All Features',
                'primary_url' => '/features',
                'secondary_text' => 'Contact Us',
                'secondary_url' => '/contact',
                'image' => self::IMG.'feature-languages.svg',
                'image_alt' => 'Two language versions of the same page',
            ]),

            B::band('', [
                B::cards([
                    ['icon' => 'languages', 'title' => 'Language packs', 'content' => 'German is included. A language pack translates the interface and brings its own version of the demo content.'],
                    ['icon' => 'globe', 'title' => 'Own URLs', 'content' => 'Each language has a prefix such as /de/ and translated addresses — good for visitors and search engines.'],
                    ['icon' => 'layers', 'title' => 'Fallback', 'content' => 'Fields you leave empty show the original. Nothing breaks while a translation is still in progress.'],
                    ['icon' => 'circle-check', 'title' => 'Translation status', 'content' => 'Lists show at a glance which pages and posts are already translated, and every language keeps its own revisions.'],
                ], ['style' => 'tinted', 'icon_style' => 'gradient']),
            ], 'py-20'),

            B::band('soft', [
                B::columns([
                    [
                        B::heading('How It Works', 'h2', 'left', 'In four steps'),
                        B::list([
                            'Activate the Multilingual plugin and a language pack',
                            'Choose the language you want to edit at the top of the editor',
                            'Translate title, text and blocks — empty fields show the original',
                            'Visitors switch languages with the language menu in the header',
                        ], 'ordered'),
                    ],
                    [B::quote('We run our website in German and English from a single installation. Translating a page is as easy as editing it.', 'Julia Brandt', 'Marketing Manager')],
                ], 'gap-12', 'center'),
            ], 'py-20'),

            B::cta(
                'Switch the Language of This Page',
                'Use the language menu in the header — this website is available in English and German.',
                'All Features', '/features',
            ),
        ];
    }

    public static function mediaFeature(): array
    {
        B::page('fmd');

        return [
            B::hero([
                'eyebrow' => 'Media Library',
                'title' => 'All Your Images in One Place',
                'text' => 'Upload once, use everywhere: in pages, blog posts, galleries and themes. With thumbnails, search and alt texts.',
                'primary_text' => 'All Features',
                'primary_url' => '/features',
                'secondary_text' => 'Page Builder',
                'secondary_url' => '/page-builder',
                'image' => self::IMG.'feature-media.svg',
                'image_alt' => 'Image grid of the media library',
            ]),

            B::band('', [
                B::cards([
                    ['icon' => 'image', 'title' => 'Central library', 'content' => 'Drag files into the browser to upload them. Thumbnails are created automatically.'],
                    ['icon' => 'search', 'title' => 'Find again', 'content' => 'Search by name, filter by type and see where an image is used — or find it from anywhere with the quick search (Ctrl K).'],
                    ['icon' => 'users', 'title' => 'Accessible', 'content' => 'Alt texts are stored with the image and used wherever it appears — in every language.'],
                ], ['align' => 'center', 'icon_style' => 'gradient']),
            ], 'py-20'),

            B::band('soft', [
                B::columns([
                    [
                        B::heading('One Picker Everywhere', 'h2', 'left', 'Workflow'),
                        B::text('<p>Wherever an image is needed — hero, card, slider, logo or blog post — the same picker opens the media library. No copying of paths, no duplicate uploads.</p>', 'lead'),
                    ],
                    [B::stats([['1', 'Library for everything'], ['0', 'Duplicate uploads']], 'cards', 'center')],
                ], 'gap-12', 'center'),
            ], 'py-20'),

            B::cta(
                'Pictures Say More Than Words',
                'The gallery plugin turns images from the library into albums for your visitors.',
                'All Features', '/features',
            ),
        ];
    }

    public static function securityFeature(): array
    {
        B::page('fsc');

        return [
            B::hero([
                'eyebrow' => 'Users & Security',
                'title' => 'Secure by Default',
                'text' => 'Roles and permissions decide who may do what. Two-factor login, revisions and an activity log keep your website and your data safe, and email addresses on the website are hidden from spam bots.',
                'primary_text' => 'All Features',
                'primary_url' => '/features',
                'secondary_text' => 'FAQ',
                'secondary_url' => '/faq',
                'image' => self::IMG.'feature-security.svg',
                'image_alt' => 'Shield with a check mark',
            ]),

            B::band('', [
                B::cards([
                    ['icon' => 'users', 'title' => 'Roles and permissions', 'content' => 'Admin, editor and user are included. Create your own roles and grant exactly the permissions they need.'],
                    ['icon' => 'shield-check', 'title' => 'Two-factor login', 'content' => 'A code by email or an authenticator app protects every account — with recovery codes for emergencies.'],
                    ['icon' => 'clock', 'title' => 'Revisions', 'content' => 'Pages and blog posts keep their earlier versions, in every language — compare, preview and restore them with one click.'],
                    ['icon' => 'file-text', 'title' => 'Activity log', 'content' => 'See who changed what and when — for audits and peace of mind.'],
                ]),
            ], 'py-20'),

            B::band('soft', [
                B::intro('Under the hood', 'Protection Built into Every Layer', '', 'left'),
                B::columns([
                    [B::list([
                        'Content Security Policy with nonces',
                        'CSRF protection on every form',
                        'Sanitized HTML in all editors',
                        'Rate limiting for login and API',
                    ])],
                    [B::list([
                        'Passwords hashed with bcrypt',
                        'Password rules you can configure',
                        'Protected files outside the public folder',
                        'Security policy and software bill of materials',
                    ])],
                ], 'gap-8'),
            ], 'py-20'),

            B::cta(
                'Questions About Security?',
                'Our FAQ answers the most common questions — or contact us directly.',
                'View FAQ', '/faq',
                'Contact Us', '/contact',
            ),
        ];
    }

    public static function developersFeature(): array
    {
        B::page('fdv');

        return [
            B::hero([
                'eyebrow' => 'For Developers',
                'title' => 'Plugins, API and Clean Code',
                'text' => 'Extend Inverys CMS with plugins, use it headless via the REST API or build your own themes — on a clean, tested Laravel codebase.',
                'primary_text' => 'Read the Docs',
                'primary_url' => '/documentation',
                'secondary_text' => 'View on GitHub',
                'secondary_url' => 'https://github.com',
                'image' => self::IMG.'blog-api.svg',
                'image_alt' => 'Connected services around an API',
            ]),

            B::band('', [
                B::cards([
                    ['icon' => 'puzzle', 'title' => 'Plugin architecture', 'content' => 'Service providers, hooks, block types, quick search results and revisions — blog, gallery, forms and languages are plugins themselves.'],
                    ['icon' => 'code', 'title' => 'REST API', 'content' => 'Pages, posts, media and settings as JSON, with token authentication, pagination and rate limits.'],
                    ['icon' => 'terminal', 'title' => 'Clean Laravel code', 'content' => 'Laravel 13, PSR-12, a comprehensive test suite and continuous integration.'],
                ], ['style' => 'tinted', 'align' => 'center', 'icon_style' => 'gradient']),
            ], 'py-20'),

            B::band('soft', [
                B::columns([
                    [
                        B::heading('Fetch Pages via the API', 'h3'),
                        B::code("curl https://example.com/api/v1/pages \\\n  -H \"Authorization: Bearer <token>\" \\\n  -H \"Accept: application/json\"", 'bash'),
                    ],
                    [
                        B::heading('Register a Plugin Block', 'h3'),
                        B::code("public function install(): void\n{\n    \$this->registerBlockType([\n        'slug' => 'team',\n        'name' => 'Team members',\n        'category' => 'content',\n    ]);\n}", 'php'),
                    ],
                ], 'gap-8'),
            ], 'py-20'),

            B::cta(
                'Start Building',
                'The documentation covers installation, themes, plugins and the API.',
                'Read the Docs', '/documentation',
                'All Features', '/features',
            ),
        ];
    }

    public static function documentation(): array
    {
        B::page('dcx');

        // The guides live in the repository (docs/) and grow with every release
        $docs = 'https://github.com/tklmn/inverys-cms/blob/master/docs/';
        $guides = ['style' => 'tinted', 'icon_style' => 'gradient'];

        return [
            B::hero([
                'eyebrow' => 'Docs',
                'title' => 'Documentation',
                'text' => 'Install Inverys CMS, build your pages, design a theme and extend it with plugins — the guides cover every step.',
                'primary_text' => 'Quick Start',
                'primary_url' => '#start',
                'secondary_text' => 'View FAQ',
                'secondary_url' => '/faq',
                'image' => self::IMG.'docs-hero.svg',
                'image_alt' => 'Documentation illustration',
            ]),

            B::band('', [
                B::intro('Guides', 'Find the Right Guide', 'Every guide lives in the docs folder of the repository and is updated with each release.'),
                B::cards([
                    ['icon' => 'book-open', 'title' => 'User Guide', 'content' => 'Installation, pages and the page builder, media, languages, users, settings and updates.', 'url' => $docs.'USER-GUIDE.md', 'link_text' => 'Read the guide'],
                    ['icon' => 'palette', 'title' => 'Theme Development', 'content' => 'Build your own theme: templates, settings, content regions, dark mode and assets.', 'url' => $docs.'THEME-DEVELOPMENT.md', 'link_text' => 'Read the guide'],
                    ['icon' => 'puzzle', 'title' => 'Plugin Development', 'content' => 'Add blocks, pages, settings, search results, revisions and API endpoints with a plugin.', 'url' => $docs.'PLUGIN-DEVELOPMENT.md', 'link_text' => 'Read the guide'],
                ], $guides),
                B::spacer('1.5rem'),
                B::cards([
                    ['icon' => 'code', 'title' => 'REST API', 'content' => 'Endpoints, tokens and examples for apps and scripts.', 'url' => $docs.'API.md', 'link_text' => 'Read the guide'],
                    ['icon' => 'shield-check', 'title' => 'Security', 'content' => 'Hardening a live site and how security issues are reported and fixed.', 'url' => $docs.'SECURITY-HARDENING.md', 'link_text' => 'Read the guide'],
                    ['icon' => 'circle-check', 'title' => 'Testing', 'content' => 'Run the test suite and write tests for your own code.', 'url' => $docs.'TESTING.md', 'link_text' => 'Read the guide'],
                ], $guides),
            ], 'py-20'),

            B::band('soft', [
                B::columns([
                    [
                        B::heading('Up and Running in Minutes', 'h2', 'left', 'Quick start'),
                        B::text('<p>Clone the repository, install the dependencies and open the site in your browser — the web installer takes over from there.</p>', 'lead'),
                        B::heading('Requirements', 'h3'),
                        B::list([
                            'PHP 8.4 or newer with the usual extensions (PDO, mbstring, OpenSSL, GD …)',
                            'Composer and Node.js to install and build',
                            'MySQL, MariaDB, PostgreSQL or SQLite',
                            'A web server whose document root points to the public folder',
                        ]),
                    ],
                    [
                        B::code("git clone https://github.com/tklmn/inverys-cms.git\ncd inverys-cms\ncomposer install\nnpm install && npm run build\nphp artisan serve\n\n# Open http://localhost:8000 — the installer starts", 'bash'),
                        B::alert('No default password', 'You create the administrator account in the installer — there are no preset credentials.', 'success'),
                    ],
                ], 'gap-12', 'center'),
            ], 'py-20', 'max-w-7xl', '', 'start'),

            B::band('', [
                B::intro('Installation', 'What the Web Installer Does'),
                B::cards([
                    ['icon' => 'gauge', 'title' => '1. Check', 'content' => 'PHP version, extensions and write permissions.'],
                    ['icon' => 'database', 'title' => '2. Connect', 'content' => 'Choose SQLite or enter the details of your database.'],
                    ['icon' => 'settings', 'title' => '3. Configure', 'content' => 'Site name, address, language and optional demo content.'],
                    ['icon' => 'user', 'title' => '4. Admin account', 'content' => 'Your own login — afterwards the installer locks itself.'],
                ], ['align' => 'center', 'icon_style' => 'gradient']),
                B::spacer('1.5rem'),
                B::text('<p>On shared hosting, upload the release package from GitHub — it already contains everything that needs a build — and open your domain: the installer starts on its own.</p>', '', 'center'),
            ], 'py-20'),

            B::band('soft', [
                B::columns([
                    [
                        B::heading('Where Things Live', 'h2', 'left', 'Project structure'),
                        B::text('<p>The core sits in app; everything that can be switched on or swapped lives in plugins and themes — updates never touch your own extensions.</p>', 'lead'),
                    ],
                    [
                        B::code("inverys-cms/\n├── app/        # core: controllers, models, services\n├── config/     # configuration\n├── database/   # migrations and seeders\n├── docs/       # the guides\n├── lang/       # English texts\n├── plugins/    # blog, forms, gallery, language-de …\n├── public/     # web root\n├── resources/  # views, CSS and JavaScript\n├── routes/     # web, API and admin routes\n├── storage/    # uploads, logs, cache\n└── themes/     # themes", 'text'),
                    ],
                ], 'gap-12', 'center'),
            ], 'py-20'),

            B::cta(
                'Need Help?',
                'Can\'t find what you need in the docs? Check the FAQ or reach out to us directly.',
                'Contact Us', '/contact',
                'View FAQ', '/faq',
            ),
        ];
    }
}
