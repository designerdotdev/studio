<?php

namespace Designer\Studio\Services;

/**
 * The onboarding templates. Each template defines one or more pages
 * composed of library sections, with optional per-instance variable
 * overrides so every template reads like a designed site rather than
 * a pile of defaults.
 *
 * Templates with an optional `layout` key seed a shared layout (header
 * sections in `before`, footer sections in `after`) applied to every
 * page; templates without one (blank) seed plain pages.
 */
class TemplateRegistry
{
    public function all(): array
    {
        return [
            'blank' => [
                'name' => 'blank',
                'title' => 'Blank',
                'description' => 'An empty canvas. Add sections as you go.',
                'pages' => [
                    [
                        'title' => 'Home',
                        'slug' => 'home',
                        'description' => '',
                        'components' => [],
                    ],
                ],
            ],

            'starter' => [
                'name' => 'starter',
                'title' => 'Starter',
                'description' => 'The essential home page — hero, features, social proof, and a call to action.',
                'layout' => [
                    'name' => 'Main',
                    'before' => $this->sections([
                        ['header-nav'],
                    ]),
                    'after' => $this->sections([
                        ['footer-simple'],
                    ]),
                ],
                'pages' => [
                    [
                        'title' => 'Home',
                        'slug' => 'home',
                        'description' => 'Starter home page',
                        'components' => $this->sections([
                            ['hero-basic'],
                            ['logos-strip'],
                            ['features-grid'],
                            ['testimonials-grid'],
                            ['cta-section'],
                        ]),
                    ],
                ],
            ],

            'launch' => [
                'name' => 'launch',
                'title' => 'Launch',
                'description' => 'A complete SaaS landing page with pricing, FAQ, and social proof.',
                'layout' => [
                    'name' => 'Main',
                    'before' => $this->sections([
                        ['header-cta'],
                    ]),
                    'after' => $this->sections([
                        ['footer-columns', [
                            'company_name' => 'Relay',
                            'blurb' => 'The shared inbox for product-minded support teams.',
                        ]],
                    ]),
                ],
                'pages' => [
                    [
                        'title' => 'Home',
                        'slug' => 'home',
                        'description' => 'SaaS product landing page',
                        'components' => $this->sections([
                            ['hero-gradient'],
                            ['logos-strip', [
                                'heading' => 'Trusted by support teams at',
                            ]],
                            ['features-bento', [
                                'eyebrow' => 'Why Relay',
                                'heading' => 'Everything a shared inbox should have been',
                                'subheading' => 'Relay keeps every customer thread, internal note, and follow-up in one calm, searchable place.',
                            ]],
                            ['stats-simple', [
                                'heading' => 'Support teams move faster on Relay',
                                'subheading' => '',
                                'stats' => [
                                    ['value' => '38%', 'label' => 'Faster first response', 'note' => 'median across teams'],
                                    ['value' => '4.2M', 'label' => 'Conversations resolved', 'note' => 'in the last 12 months'],
                                    ['value' => '99.95%', 'label' => 'Uptime', 'note' => 'trailing 90 days'],
                                    ['value' => '4.8/5', 'label' => 'Support rating', 'note' => 'from 3,100 reviews'],
                                ],
                            ]],
                            ['testimonial-spotlight', [
                                'quote' => 'We answered 40% more conversations in our first month on Relay — without adding headcount. The shared context changed how our team works.',
                                'name' => 'Camille Fournier',
                                'role' => 'Head of Support',
                                'company' => 'Meridian',
                            ]],
                            ['pricing-tiers'],
                            ['faq-accordion'],
                            ['cta-panel', [
                                'heading' => 'Move your team into Relay',
                                'text' => 'Import your inbox in an afternoon. Your customers won\'t notice a thing — your team will.',
                                'button_text' => 'Start for free',
                                'button_text_2' => 'Book a demo',
                            ]],
                        ]),
                    ],
                ],
            ],

            'studio' => [
                'name' => 'studio',
                'title' => 'Studio',
                'description' => 'An editorial portfolio for agencies and creative practices.',
                'layout' => [
                    'name' => 'Main',
                    'before' => $this->sections([
                        ['header-simple'],
                    ]),
                    'after' => $this->sections([
                        ['footer-simple', [
                            'company_name' => 'Foundry',
                            'copyright' => '© 2026 Foundry ApS. All rights reserved.',
                        ]],
                    ]),
                ],
                'pages' => [
                    [
                        'title' => 'Home',
                        'slug' => 'home',
                        'description' => 'Agency portfolio',
                        'components' => $this->sections([
                            ['hero-editorial'],
                            ['gallery-masonry', [
                                'eyebrow' => 'Selected work',
                                'heading' => 'Recent projects',
                                'subheading' => 'Identity systems, product interfaces, and the occasional website we\'re quietly proud of.',
                            ]],
                            ['features-split', [
                                'eyebrow' => 'Services',
                                'heading' => 'What we take on',
                                'subheading' => 'Three disciplines, practiced together.',
                                'rows' => [
                                    [
                                        'title' => 'Brand identity',
                                        'description' => 'Naming, visual systems, and guidelines that survive contact with real teams. We design identities to be used, not framed.',
                                        'image' => 'https://picsum.photos/seed/foundry-brand/1100/860',
                                        'link_text' => 'See identity work',
                                        'link_url' => '#',
                                    ],
                                    [
                                        'title' => 'Product design',
                                        'description' => 'Interfaces for software people use every day. We work in flows and prototypes, embedded with your engineers.',
                                        'image' => 'https://picsum.photos/seed/foundry-product/1100/860',
                                        'link_text' => 'See product work',
                                        'link_url' => '#',
                                    ],
                                    [
                                        'title' => 'Web engineering',
                                        'description' => 'Marketing sites and design systems built to be maintained. Fast, accessible, and handed over with documentation.',
                                        'image' => 'https://picsum.photos/seed/foundry-web/1100/860',
                                        'link_text' => 'See web work',
                                        'link_url' => '#',
                                    ],
                                ],
                            ]],
                            ['stats-panel', [
                                'heading' => 'A small practice with a long memory',
                                'text' => 'Founded in 2019. Independent, senior-only, and deliberately small.',
                                'stats' => [
                                    ['value' => '74', 'label' => 'Projects shipped'],
                                    ['value' => '11', 'label' => 'Design awards'],
                                    ['value' => '87%', 'label' => 'Clients who return'],
                                ],
                            ]],
                            ['testimonial-spotlight', [
                                'quote' => 'Foundry rebuilt our identity and product in nine weeks. It\'s rare to find a studio that sweats the strategy and the pixels equally.',
                                'name' => 'Marta Jensen',
                                'role' => 'CEO',
                                'company' => 'Corelink',
                            ]],
                            ['contact-split', [
                                'eyebrow' => 'Contact',
                                'heading' => 'Start a project',
                                'subheading' => 'Tell us what you\'re making. We take on four engagements per quarter.',
                                'email' => 'hello@foundry.studio',
                                'phone' => '+45 31 82 47 19',
                                'address' => "Fabrikmestervej 4\n1437 Copenhagen K, Denmark",
                            ]],
                        ]),
                    ],
                ],
            ],

            'horizon' => [
                'name' => 'horizon',
                'title' => 'Horizon',
                'description' => 'A three-page company site — Home, About, and Contact.',
                'layout' => [
                    'name' => 'Main',
                    'before' => $this->sections([
                        ['header-nav', $this->harborHeader()],
                    ]),
                    'after' => $this->sections([
                        ['footer-columns', $this->harborFooter()],
                    ]),
                ],
                'pages' => [
                    [
                        'title' => 'Home',
                        'slug' => 'home',
                        'description' => 'Company home page',
                        'components' => $this->sections([
                            ['hero-split', [
                                'badge_text' => 'New — Route optimization',
                                'heading' => 'Freight operations without the phone tag',
                                'text' => 'Harbor gives shippers and carriers one live view of every load — quotes, documents, tracking, and settlement in a single workspace.',
                                'button_text' => 'Get a demo',
                                'button_text_2' => 'See how it works',
                                'bullets' => [
                                    ['text' => 'Live tracking on every shipment'],
                                    ['text' => 'Automated documents and settlement'],
                                    ['text' => 'Works with your existing TMS'],
                                ],
                                'image' => 'https://picsum.photos/seed/harbor-ops/1160/1000',
                            ]],
                            ['logos-grid', [
                                'heading' => 'Moving freight for',
                            ]],
                            ['features-grid', [
                                'eyebrow' => 'Platform',
                                'heading' => 'One workspace for every load',
                                'subheading' => 'From quote to proof of delivery, Harbor keeps ops, finance, and customers on the same page.',
                            ]],
                            ['stats-simple', [
                                'heading' => 'Operations teams run leaner on Harbor',
                                'subheading' => '',
                                'stats' => [
                                    ['value' => '$2.1B', 'label' => 'Freight under management', 'note' => 'annualized'],
                                    ['value' => '18,700', 'label' => 'Active lanes', 'note' => 'across North America'],
                                    ['value' => '31%', 'label' => 'Fewer check calls', 'note' => 'median after 60 days'],
                                    ['value' => '6 min', 'label' => 'Average quote time', 'note' => 'down from 4 hours'],
                                ],
                            ]],
                            ['faq-columns'],
                            ['cta-split', [
                                'heading' => 'See Harbor on your own lanes',
                                'text' => 'Bring three recent loads to the demo — we\'ll show you the difference live.',
                                'button_text' => 'Book a demo',
                                'button_text_2' => 'Talk to sales',
                            ]],
                        ]),
                    ],
                    [
                        'title' => 'About',
                        'slug' => 'about',
                        'description' => 'About the company',
                        'components' => $this->sections([
                            ['content-prose', [
                                'eyebrow' => 'About Harbor',
                                'heading' => 'Built by people who ran the night desk',
                                'lead' => 'Harbor started in 2021, in a Cleveland brokerage where three of our founders spent years chasing trucks by phone at 2am.',
                            ]],
                            ['content-split', [
                                'eyebrow' => 'Our approach',
                                'heading' => 'Software that respects the way freight actually moves',
                                'link_text' => 'Read our operating principles',
                            ]],
                            ['team-grid', [
                                'eyebrow' => 'Team',
                                'heading' => 'The people behind Harbor',
                                'subheading' => 'A team of operators, dispatchers, and engineers across four time zones.',
                            ]],
                            ['cta-section', [
                                'heading' => 'We\'re hiring across ops and engineering',
                                'text' => 'Remote-first, with hubs in Cleveland and Toronto.',
                                'button_text' => 'View open roles',
                                'button_text_2' => '',
                                'footnote' => '',
                            ]],
                        ]),
                    ],
                    [
                        'title' => 'Contact',
                        'slug' => 'contact',
                        'description' => 'Contact page',
                        'components' => $this->sections([
                            ['contact-split', [
                                'eyebrow' => 'Contact',
                                'heading' => 'Talk to the Harbor team',
                                'subheading' => 'Questions about pricing, onboarding, or a specific lane? We answer fast.',
                                'email' => 'hello@harbor.dev',
                                'phone' => '+1 (216) 555-0114',
                                'address' => "1100 Superior Ave, Suite 900\nCleveland, OH 44114",
                            ]],
                            ['faq-columns', [
                                'eyebrow' => 'Common questions',
                                'heading' => 'Before you write in',
                                'subheading' => '',
                            ]],
                        ]),
                    ],
                ],
            ],
        ];
    }

    public function find(string $name): ?array
    {
        return $this->all()[$name] ?? null;
    }

    /**
     * Expand a compact [[ref, overrides?], …] list into component instances.
     */
    protected function sections(array $defs): array
    {
        $components = [];

        foreach ($defs as $order => $def) {
            $components[] = [
                'id' => null,
                'component_ref' => $def[0],
                'order' => $order,
                'variables' => $def[1] ?? [],
            ];
        }

        return $components;
    }

    protected function harborHeader(): array
    {
        return [
            'company_name' => 'Harbor',
            'nav_links' => [
                ['text' => 'Home', 'url' => '/'],
                ['text' => 'About', 'url' => '/about'],
                ['text' => 'Contact', 'url' => '/contact'],
            ],
            'button_1_text' => 'Log in',
            'button_2_text' => 'Get a demo',
        ];
    }

    protected function harborFooter(): array
    {
        return [
            'company_name' => 'Harbor',
            'blurb' => 'The operations platform for modern freight teams.',
            'copyright' => '© 2026 Harbor Technologies, Inc. All rights reserved.',
        ];
    }
}
