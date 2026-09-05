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

            'atlas' => [
                'name' => 'atlas',
                'title' => 'Atlas',
                'description' => 'A warm, editorial SaaS landing page with a serif voice — the recommended starting point.',
                'layout' => [
                    'name' => 'Main',
                    'before' => $this->sections([
                        ['atlas-header'],
                    ]),
                    'after' => $this->sections([
                        ['atlas-footer'],
                    ]),
                ],
                'pages' => [
                    [
                        'title' => 'Home',
                        'slug' => 'home',
                        'description' => 'Atlas landing page',
                        'components' => $this->sections([
                            ['atlas-hero'],
                            ['atlas-logos'],
                            ['atlas-steps'],
                            ['atlas-features'],
                            ['atlas-stats'],
                            ['atlas-pricing'],
                            ['atlas-testimonials'],
                            ['atlas-faq'],
                            ['atlas-cta'],
                        ]),
                    ],
                ],
            ],

            'pilot' => [
                'name' => 'pilot',
                'title' => 'Pilot',
                'description' => 'An off-white, monochrome site for an AI agent framework — a three-pane console on a painted landscape, dark dropdown menus, pricing, and a guides library.',
                'layout' => [
                    'name' => 'Main',
                    'before' => $this->sections([
                        ['pilot-header'],
                    ]),
                    'after' => $this->sections([
                        ['pilot-footer'],
                    ]),
                ],
                'pages' => [
                    [
                        'title' => 'Home',
                        'slug' => 'home',
                        'description' => 'Pilot is an agent framework for real work — a durable loop, typed tools, checkpointed state, human review, and a trace of every decision your agents make.',
                        'components' => $this->sections([
                            ['pilot-hero'],
                            ['pilot-logos'],
                            ['pilot-steps'],
                            ['pilot-bento'],
                            ['pilot-split'],
                            ['pilot-stats'],
                            ['pilot-split', [
                                'eyebrow' => 'The loop',
                                'heading' => 'A run you can pause, inspect, and resume',
                                'text' => 'Every step is checkpointed. When a tool times out or a human needs to approve something, the run parks itself instead of dying and losing an hour of work.',
                                'point_1' => 'Checkpoints after every step, resumable for days',
                                'point_2' => 'Human approval gates anywhere in the loop',
                                'point_3' => 'Replay any run against a new prompt or model',
                                'link_text' => 'Read the loop guide',
                                'link_url' => '/designing-an-agent-loop',
                                'visual_left' => true,
                                'show_ruler' => true,
                                'visual_title' => 'Run 8f21c',
                                'visual_chip' => '4m 12s',
                                'visual_note' => 'Every phase replayable in isolation.',
                                'items' => [
                                    ['label' => 'Plan and fan out', 'value' => '0–24s', 'start' => '0', 'width' => '10', 'featured' => ''],
                                    ['label' => 'Tools, 84 batches', 'value' => '24–186s', 'start' => '10', 'width' => '64', 'featured' => '1'],
                                    ['label' => 'Awaiting approval', 'value' => '186–214s', 'start' => '74', 'width' => '11', 'featured' => ''],
                                    ['label' => 'Verify and commit', 'value' => '214–252s', 'start' => '85', 'width' => '15', 'featured' => ''],
                                ],
                            ]],
                            ['pilot-testimonials'],
                            ['pilot-cta'],
                        ]),
                    ],
                    [
                        'title' => 'Features',
                        'slug' => 'features',
                        'description' => 'Everything Pilot gives you — a durable loop, typed tools, persistent state, human approval gates, replayable traces, evals in CI, and metered spend.',
                        'components' => $this->sections([
                            ['pilot-page-header'],
                            ['pilot-feature-rows'],
                            ['pilot-integrations'],
                            ['pilot-cta', [
                                'heading' => 'Wire one tool and watch the trace',
                                'text' => 'The runtime is open source. Install it, give an agent a single function to call, and read what comes back before you commit to anything.',
                                'button_2_text' => 'Read the guides',
                                'button_2_link' => '/guides',
                                'note' => 'Apache 2.0 · No account needed to start',
                            ]],
                        ]),
                    ],
                    [
                        'title' => 'Pricing',
                        'slug' => 'pricing',
                        'description' => 'The Pilot runtime is open source and always will be. Cloud adds hosted traces, evals in CI, approval routing, and spend metering.',
                        'components' => $this->sections([
                            ['pilot-page-header', [
                                'eyebrow' => 'Pricing',
                                'heading' => 'The runtime is free. Always.',
                                'text' => 'Pilot is Apache 2.0 and runs entirely on your own infrastructure. You pay only if you would rather not operate the trace store, the eval runner, and the approval queue yourself.',
                                'meta_value' => 'Apache 2.0',
                                'meta_label' => 'no strings, no rug pull',
                            ]],
                            ['pilot-pricing'],
                            ['pilot-comparison'],
                            ['pilot-faq'],
                            ['pilot-cta', [
                                'heading' => 'Start with the open-source runtime',
                                'text' => 'Nothing to sign up for. Add the dependency, write one tool, and decide about the hosted parts later.',
                                'button_1_link' => '#',
                                'note' => 'Apache 2.0 · Bring your own models · Self-host everything',
                            ]],
                        ]),
                    ],
                    [
                        'title' => 'Guides',
                        'slug' => 'guides',
                        'description' => 'Practical writing on agent engineering — designing a loop that survives production, writing tools that fail well, evaluating agents properly, and what a run really costs.',
                        'components' => $this->sections([
                            ['pilot-page-header', [
                                'eyebrow' => 'Guides',
                                'heading' => 'How to build agents that survive production',
                                'text' => 'Everything here comes from watching a lot of agent runs fail in interesting ways. No prompt tricks — just the loop, the tools, the tests, and the bill.',
                                'show_meta' => false,
                            ]],
                            ['pilot-guides'],
                            ['pilot-cta', [
                                'heading' => 'Or skip the reading and run one',
                                'text' => 'Every idea in these guides is already built into the runtime. Install it and the defaults are the advice.',
                                'button_2_text' => 'See the runtime',
                                'button_2_link' => '/features',
                                'note' => 'Apache 2.0 · No account needed to start',
                            ]],
                        ]),
                    ],
                    ...$this->pilotGuidePages(),
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

    /**
     * The four Pilot guides, each its own page built from a single
     * pilot-article section. The first guide's body is the section's
     * field default; the other three override it here.
     */
    protected function pilotGuidePages(): array
    {
        $guides = [
            [
                'slug' => 'designing-an-agent-loop',
                'title' => 'Designing an agent loop that survives production',
                'description' => 'Almost every agent that dies in production dies the same way. The fix is not a smarter model — it is a loop built from steps that can each fail independently.',
                'variables' => [],
            ],
            [
                'slug' => 'tools-that-fail-well',
                'title' => 'Tools that fail well',
                'variables' => [
                    'category' => 'Tools',
                    'date' => '28 January 2026',
                    'read_time' => '7 min read',
                    'heading' => 'Tools that fail well',
                    'text' => 'The model will pass your tool something absurd — reliably, on a long enough timeline. Whether that becomes a corrected second attempt or a support ticket is entirely down to how the tool is written.',
                    'author' => 'Theo Adeyemi',
                    'author_role' => 'Platform lead, Cadence',
                    'author_image' => 'https://i.pravatar.cc/144?img=59',
                    'content' => <<<'HTML'
<p class="lead">The model will pass your tool something absurd. Not maybe — reliably, on a long enough timeline. A tool that fails well turns that into a corrected second attempt; a tool that fails badly turns it into a support ticket.</p>

<h2>Make bad arguments impossible before they are handled</h2>

<p>The cheapest validation is a type. If a tool takes an enum, the model cannot invent a fourth option. If it takes a value object with a constructor that rejects negatives, no negative ever reaches your logic.</p>

<p>Push as much correctness as you can into the signature, because the signature is also the schema the model sees. Every constraint you express there is a constraint the model is told about up front, rather than one it discovers by failing.</p>

<h2>Return errors the model can act on</h2>

<p>Compare two failures:</p>

<ul>
    <li><code>Error: invalid argument</code></li>
    <li><code>chargeId "ch_9f2" was not found. Charge IDs look like "ch_" followed by 24 characters. Use search_charges to find one by email.</code></li>
</ul>

<p>The first ends the run. The second is a hint, and the model will usually take it and succeed on the next attempt. Write tool errors as if you were leaving a note for a capable colleague who cannot see your codebase.</p>

<h2>Be idempotent, then say so</h2>

<p>Retries are a fact of agent systems, so every tool that changes something needs an idempotency key. Take one as an argument, make repeats safe, and document that in the description — the model then knows a retry is cheap, and so do you.</p>

<h3>Keep tools narrow</h3>

<p>A tool called <code>manage_customer</code> that takes an <code>action</code> string is four tools wearing a trench coat. Split it. Narrow tools have clearer schemas, better error messages, and the model picks between them far more reliably than it picks between modes of one overloaded call.</p>

<h2>Log the arguments, always</h2>

<p>When something strange happens, the first question is always "what did it actually pass?" Record the arguments to every call as part of the trace. It costs nothing and it is the difference between a five-minute diagnosis and an afternoon.</p>
HTML,
                ],
            ],
            [
                'slug' => 'evaluating-agents',
                'title' => 'How to actually evaluate an agent',
                'variables' => [
                    'category' => 'Evaluation',
                    'date' => '9 February 2026',
                    'read_time' => '8 min read',
                    'heading' => 'How to actually evaluate an agent',
                    'text' => 'Most agent evaluation is a number that goes up. When it goes down you have no idea which of the eleven steps got worse — here is the boring, specific alternative.',
                    'author' => 'Priya Raman',
                    'author_role' => 'Founder, Vector Field',
                    'author_image' => 'https://i.pravatar.cc/144?img=32',
                    'content' => <<<'HTML'
<p class="lead">Most agent evaluation is a number that goes up. That number is nearly useless, because when it goes down you have no idea which of the eleven steps got worse.</p>

<p>Useful evaluation is boring and specific: assertions on steps, run on every change, attributed to a cause.</p>

<h2>Start from runs you have already seen</h2>

<p>You do not need a synthetic benchmark. You need the twenty runs from last month that went wrong, frozen as fixtures. Promote a recorded run to a test case: same inputs, same tool responses, replayed against your current prompt and model.</p>

<p>This gives you something a benchmark never does — regression tests for the specific failures your users actually hit.</p>

<h2>Assert on steps, not just outcomes</h2>

<p>Outcome-level assertions tell you something broke. Step-level assertions tell you what:</p>

<ul>
    <li>Did it call <code>search_charges</code> before <code>refund</code>?</li>
    <li>Did it stop after the approval gate rather than proceeding?</li>
    <li>Did it stay under six steps?</li>
    <li>Was the refund amount exactly the charge amount?</li>
</ul>

<p>Each of these fails loudly and points at one place. "Score dropped from 0.82 to 0.79" points at nothing.</p>

<h2>Separate the three things you are testing</h2>

<p>A regression comes from the prompt, the model, or the tools — and if you change more than one at a time you will not know which. Pin two, vary the third. It is slower for an afternoon and much faster for a quarter.</p>

<h3>Run it where you run your other tests</h3>

<p>An eval suite that lives in a notebook gets run when someone remembers. One that runs on every pull request gets run always. Put it in CI with everything else, and treat a failing eval exactly like a failing unit test.</p>

<h2>Watch cost and latency as first-class results</h2>

<p>An agent that gets the right answer in forty steps and eleven dollars has regressed, even if the assertion passes. Record tokens, wall-clock, and spend per run in the same report as correctness, and set ceilings on all three.</p>

<p>None of this is sophisticated. It is just testing, applied to a system that happens to be stochastic — and the teams shipping agents are the ones treating it that way.</p>
HTML,
                ],
            ],
            [
                'slug' => 'the-cost-of-a-run',
                'title' => 'What a run really costs',
                'variables' => [
                    'category' => 'Cost',
                    'date' => '20 February 2026',
                    'read_time' => '6 min read',
                    'heading' => 'What a run really costs',
                    'text' => 'The bill is never the model. It is the retry you did not notice, the context you resent forty times, and the planning step running a frontier model to pick between three tools.',
                    'author' => 'Jonas Meyer',
                    'author_role' => 'Backend engineer, Substrate',
                    'author_image' => 'https://i.pravatar.cc/144?img=14',
                    'content' => <<<'HTML'
<p class="lead">The bill is never the model. It is the retry you did not notice, the context you resent forty times, and the planning step that runs a frontier model to decide which of three tools to call.</p>

<h2>Attribute cost to steps</h2>

<p>A per-run total tells you that something is expensive. Per-step attribution tells you which loop to fix. In practice the distribution is brutally uneven — one step is usually most of the bill, and it is rarely the one you would guess.</p>

<h2>The four things that actually cost money</h2>

<ul>
    <li><strong>Resent context.</strong> Every step replays the conversation. A run with thirty steps and a fat system prompt pays for that prompt thirty times. Trim it, or summarise the middle of long runs.</li>
    <li><strong>Silent retries.</strong> A tool that fails intermittently can triple a run's cost while the success rate looks fine. Count retries in your metrics, not just outcomes.</li>
    <li><strong>Over-modelled planning.</strong> Choosing between three tools does not need your most expensive model. Route per step.</li>
    <li><strong>Re-derivation.</strong> Agents recompute things they already worked out. Cache tool results by argument hash within a run and the same lookup stops being billed twice.</li>
</ul>

<h2>Route models per step</h2>

<p>The single biggest saving available to most teams: use a small fast model for planning, classification, and extraction, and reserve the expensive one for the step that writes or decides something. This is usually a three-to-five-times reduction with no measurable quality loss, and it takes an afternoon.</p>

<h3>Set a ceiling and mean it</h3>

<p>Give every run a hard budget. When it hits, halt and park rather than continue. Runs that hit the ceiling are almost always stuck in a loop, so the budget doubles as your best early-warning signal for a broken prompt.</p>

<h2>Measure the thing you actually care about</h2>

<p>Cost per run is the wrong denominator. Cost per <em>successful</em> run is the right one — an agent that is cheap and wrong is not cheap. Track both, and watch the gap: when it widens, something has started failing quietly.</p>
HTML,
                ],
            ],
        ];

        return array_map(fn (array $guide) => [
            'title' => $guide['title'],
            'slug' => $guide['slug'],
            'description' => $guide['description'] ?? $guide['variables']['text'],
            'components' => $this->sections([
                ['pilot-article', $guide['variables']],
            ]),
        ], $guides);
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
