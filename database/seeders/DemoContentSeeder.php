<?php

namespace Database\Seeders;

use App\Models\Page;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class DemoContentSeeder extends Seeder
{
    public function run(): void
    {
        // Skip if we already have showcase pages
        if (Page::where('slug', 'portfolio')->exists()) {
            $this->command->info('Demo content already exists, skipping.');

            return;
        }

        $author = User::first();

        // Create showcase pages
        $this->seedShowcasePages($author);

        $this->command->info('Demo content seeded successfully.');
    }

    private function seedShowcasePages(?User $author): void
    {
        $showcasePages = [
            [
                'title' => 'Portfolio',
                'slug' => 'portfolio',
                'meta_description' => 'Our work showcase featuring recent projects and case studies.',
                'content_type' => 'blocks',
                'blocks' => [
                    [
                        'id' => Str::uuid()->toString(),
                        'type' => 'container',
                        'settings' => ['max_width' => 'max-w-6xl', 'padding' => 'py-8'],
                        'children' => [
                            [
                                'id' => Str::uuid()->toString(),
                                'type' => 'heading',
                                'data' => ['text' => 'Our Work', 'level' => 'h2', 'alignment' => 'center'],
                                'settings' => [],
                            ],
                            [
                                'id' => Str::uuid()->toString(),
                                'type' => 'text',
                                'data' => ['content' => '<p class="text-center text-gray-600">A selection of projects that showcase our expertise and attention to detail.</p>'],
                                'settings' => [],
                            ],
                            [
                                'id' => Str::uuid()->toString(),
                                'type' => 'columns',
                                'settings' => ['columns' => '3', 'gap' => 'gap-6'],
                                'children' => [
                                    [
                                        'id' => Str::uuid()->toString(),
                                        'type' => 'column',
                                        'settings' => [],
                                        'children' => [
                                            ['id' => Str::uuid()->toString(), 'type' => 'card', 'data' => ['title' => 'E-Commerce Platform', 'content' => 'A modern online store built with performance and conversion in mind.', 'image' => 'https://picsum.photos/seed/project1/600/400'], 'settings' => []],
                                        ],
                                    ],
                                    [
                                        'id' => Str::uuid()->toString(),
                                        'type' => 'column',
                                        'settings' => [],
                                        'children' => [
                                            ['id' => Str::uuid()->toString(), 'type' => 'card', 'data' => ['title' => 'Corporate Website', 'content' => 'Clean, professional design for a leading consulting firm.', 'image' => 'https://picsum.photos/seed/project2/600/400'], 'settings' => []],
                                        ],
                                    ],
                                    [
                                        'id' => Str::uuid()->toString(),
                                        'type' => 'column',
                                        'settings' => [],
                                        'children' => [
                                            ['id' => Str::uuid()->toString(), 'type' => 'card', 'data' => ['title' => 'SaaS Dashboard', 'content' => 'An intuitive analytics dashboard with real-time data visualization.', 'image' => 'https://picsum.photos/seed/project3/600/400'], 'settings' => []],
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
            [
                'title' => 'Team',
                'slug' => 'team',
                'meta_description' => 'Meet the talented people behind our projects.',
                'content_type' => 'blocks',
                'blocks' => [
                    [
                        'id' => Str::uuid()->toString(),
                        'type' => 'container',
                        'settings' => ['max_width' => 'max-w-6xl', 'padding' => 'py-8'],
                        'children' => [
                            [
                                'id' => Str::uuid()->toString(),
                                'type' => 'heading',
                                'data' => ['text' => 'Our Team', 'level' => 'h2', 'alignment' => 'center'],
                                'settings' => [],
                            ],
                            [
                                'id' => Str::uuid()->toString(),
                                'type' => 'columns',
                                'settings' => ['columns' => '3', 'gap' => 'gap-6'],
                                'children' => [
                                    [
                                        'id' => Str::uuid()->toString(),
                                        'type' => 'column',
                                        'settings' => [],
                                        'children' => [
                                            ['id' => Str::uuid()->toString(), 'type' => 'card', 'data' => ['title' => 'Alex Chen', 'content' => 'Lead Developer — Full-stack engineer with a passion for clean architecture.', 'image' => 'https://picsum.photos/seed/team1/400/400'], 'settings' => []],
                                        ],
                                    ],
                                    [
                                        'id' => Str::uuid()->toString(),
                                        'type' => 'column',
                                        'settings' => [],
                                        'children' => [
                                            ['id' => Str::uuid()->toString(), 'type' => 'card', 'data' => ['title' => 'Sarah Miller', 'content' => 'Designer — Creating beautiful, user-centered interfaces since 2015.', 'image' => 'https://picsum.photos/seed/team2/400/400'], 'settings' => []],
                                        ],
                                    ],
                                    [
                                        'id' => Str::uuid()->toString(),
                                        'type' => 'column',
                                        'settings' => [],
                                        'children' => [
                                            ['id' => Str::uuid()->toString(), 'type' => 'card', 'data' => ['title' => 'James Park', 'content' => 'DevOps — Keeping our infrastructure fast, reliable, and secure.', 'image' => 'https://picsum.photos/seed/team3/400/400'], 'settings' => []],
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
            [
                'title' => 'Pricing',
                'slug' => 'pricing',
                'meta_description' => 'Simple, transparent pricing for every stage of your project.',
                'content_type' => 'blocks',
                'blocks' => [
                    [
                        'id' => Str::uuid()->toString(),
                        'type' => 'container',
                        'settings' => ['max_width' => 'max-w-6xl', 'padding' => 'py-8'],
                        'children' => [
                            [
                                'id' => Str::uuid()->toString(),
                                'type' => 'heading',
                                'data' => ['text' => 'Pricing', 'level' => 'h2', 'alignment' => 'center'],
                                'settings' => [],
                            ],
                            [
                                'id' => Str::uuid()->toString(),
                                'type' => 'text',
                                'data' => ['content' => '<p class="text-center text-gray-600 mb-8">Choose the plan that fits your needs. All plans include core CMS features.</p>'],
                                'settings' => [],
                            ],
                            [
                                'id' => Str::uuid()->toString(),
                                'type' => 'columns',
                                'settings' => ['columns' => '3', 'gap' => 'gap-6'],
                                'children' => [
                                    [
                                        'id' => Str::uuid()->toString(),
                                        'type' => 'column',
                                        'settings' => [],
                                        'children' => [
                                            ['id' => Str::uuid()->toString(), 'type' => 'card', 'data' => ['title' => 'Starter — Free', 'content' => 'Perfect for personal sites and small projects. Includes page builder, theme system, and media library.'], 'settings' => []],
                                            ['id' => Str::uuid()->toString(), 'type' => 'button', 'data' => ['text' => 'Get Started', 'url' => '/register', 'style' => 'outline'], 'settings' => []],
                                        ],
                                    ],
                                    [
                                        'id' => Str::uuid()->toString(),
                                        'type' => 'column',
                                        'settings' => [],
                                        'children' => [
                                            ['id' => Str::uuid()->toString(), 'type' => 'card', 'data' => ['title' => 'Professional — $29/mo', 'content' => 'For growing businesses. Adds API access, advanced RBAC, blog, and plugin ecosystem.'], 'settings' => []],
                                            ['id' => Str::uuid()->toString(), 'type' => 'button', 'data' => ['text' => 'Start Free Trial', 'url' => '/register', 'style' => 'primary'], 'settings' => []],
                                        ],
                                    ],
                                    [
                                        'id' => Str::uuid()->toString(),
                                        'type' => 'column',
                                        'settings' => [],
                                        'children' => [
                                            ['id' => Str::uuid()->toString(), 'type' => 'card', 'data' => ['title' => 'Enterprise — Custom', 'content' => 'Tailored solutions with dedicated support, custom theme development, and SLA guarantees.'], 'settings' => []],
                                            ['id' => Str::uuid()->toString(), 'type' => 'button', 'data' => ['text' => 'Contact Us', 'url' => '/contact', 'style' => 'secondary'], 'settings' => []],
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        foreach ($showcasePages as $pageData) {
            Page::firstOrCreate(
                ['slug' => $pageData['slug']],
                array_merge($pageData, [
                    'is_active' => true,
                    'include_in_search' => true,
                ])
            );
        }
    }
}
