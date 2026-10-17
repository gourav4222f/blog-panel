<?php

/**
 * Generates blogs/database/seed.sql (sample content for a fresh BlogFlow install).
 *   php scripts/make-seed.php
 *
 * Post HTML is run through BlogFlow's own sanitiser so the stored HTML, search text, word
 * counts and reading times match what the admin editor would save.
 */

declare(strict_types=1);

define('BLOGFLOW', true);
define('BF_ROOT', dirname(__DIR__) . '/blogs');
require BF_ROOT . '/vendor/autoload.php';

use BlogFlow\Services\HtmlSanitizer;

const P = 'bf_';

function q(?string $v): string
{
    if ($v === null) {
        return 'NULL';
    }
    return "'" . str_replace(['\\', "'", "\r", "\n", "\0"], ['\\\\', "\\'", '\\r', '\\n', '\\0'], $v) . "'";
}

function t(string $table): string
{
    return '`' . P . $table . '`';
}

$categories = [
    // slug => [name, description, sort, parent slug]
    'travel-guides' => ['Travel Guides', 'Destination guides, seasonal advice and practical tips for planning a smoother trip.', 1, null],
    'goa' => ['Goa', 'Beaches, seasons and getting around Goa.', 2, 'travel-guides'],
    'taxi-services' => ['Taxi Services', 'How airport transfers, outstation trips and local rides work — and how to book with confidence.', 3, null],
];

$tags = [
    'airport' => 'Airport', 'taxi' => 'Taxi', 'goa' => 'Goa', 'road-trip' => 'Road trip', 'family-travel' => 'Family travel',
    'travel-tips' => 'Travel tips', 'safety' => 'Safety', 'packing' => 'Packing', 'monsoon' => 'Monsoon', 'festivals' => 'Festivals',
];

$authors = [
    'editorial-team' => ['Editorial Team', 'Editors', 'Our editorial team writes practical, independent guides to help travellers plan comfortable journeys. Articles are reviewed and updated when advice changes.'],
    'arjun-mehta' => ['Arjun Mehta', 'Senior Driver & Route Planner', 'Arjun has planned airport transfers and long-distance road trips for years, and shares what he has learned about travelling comfortably and safely.'],
];

$faqAirport = [
    ['q' => 'How early should I book an airport taxi?', 'a' => 'Book as soon as your flight times are confirmed. For very early or late flights, and during holidays, booking a day or more in advance is the safest option.'],
    ['q' => 'What if my flight is delayed?', 'a' => 'Share your flight number when you book. A good taxi service tracks arrival times and adjusts the pickup instead of leaving when the original time passes.'],
    ['q' => 'Can I ask for a child seat?', 'a' => 'Usually yes, but it must be requested in advance so the right vehicle and seat can be arranged.'],
];

// [slug, title, excerpt, category, author, status, days from now, featured, tags, related slugs, focus keyword, meta description, faq, html]
$posts = [
    [
        'airport-taxi-booking-guide',
        'Airport Transfers Made Easy: How to Book a Reliable Taxi',
        'Everything you need to know about booking an airport taxi: when to book, what details to share, and how to make the pickup stress-free.',
        'taxi-services', 'arjun-mehta', 'published', -2, true, ['airport', 'taxi', 'travel-tips'], ['early-morning-airport-pickup', 'outstation-cab-journey-guide'],
        'airport taxi', 'How to book a reliable airport taxi: when to book, what to share with your driver, and simple steps for a calm, on-time pickup.', $faqAirport,
        <<<'HTML'
<p>An airport transfer is often the first and last part of a trip, and it sets the tone for everything in between. A little preparation turns it from a source of stress into the easiest part of your journey.</p>
<h2>When to book</h2>
<p>Book as soon as your flight times are confirmed. Early-morning departures, late-night arrivals and holiday weekends are the busiest times, and booking ahead gives the service time to plan the right vehicle and driver for you.</p>
<h2>What details to share</h2>
<p>The more your driver knows, the smoother the pickup. When you book, include:</p>
<ul>
<li>Your flight number and scheduled arrival or departure time</li>
<li>The exact pickup address, with landmarks if the location is hard to find</li>
<li>The number of passengers and pieces of luggage</li>
<li>Any special needs, such as a child seat or extra space for a wheelchair</li>
</ul>
<h3>Choosing the right vehicle</h3>
<p>A compact car is fine for one or two travellers with cabin bags. Families with large suitcases, golf bags or prams are far more comfortable in an SUV or a van, so mention your luggage honestly when booking.</p>
<h2>Timing your departure</h2>
<p>For departures, work backwards from the airline's check-in deadline. Add the expected travel time, then a buffer for traffic, weather and finding the right terminal. It is always better to wait twenty minutes at the airport than to rush through security.</p>
<blockquote><p>Tip: save your driver's contact number before the day of travel so you can reach them quickly if plans change.</p></blockquote>
<h2>On arrival</h2>
<p>After landing, switch on your phone and check for a message from your driver. Many services wait at a meeting point outside arrivals rather than at the gate, so read the pickup instructions before you collect your bags.</p>
<h3>If your flight is delayed</h3>
<p>Because you shared your flight number, a reliable service will track the new arrival time. You do not need to call unless your plans change, for example if you miss a connection and arrive on a different flight.</p>
<h2>A simple checklist</h2>
<ol>
<li>Book with your flight details as early as possible.</li>
<li>Confirm the pickup time and meeting point the day before.</li>
<li>Keep your phone charged and your driver's number saved.</li>
<li>Allow extra time during rush hour and bad weather.</li>
</ol>
<p>With these steps covered, you can focus on your trip and leave the driving to someone who knows the road.</p>
HTML,
    ],
    [
        'goa-travel-guide-first-timers',
        'A First-Timer’s Guide to Goa: When to Go and How to Get Around',
        'Planning your first trip to Goa? Learn how the seasons differ, which areas suit different travellers, and the easiest ways to get around.',
        'goa', 'editorial-team', 'published', -6, false, ['goa', 'travel-tips', 'road-trip'], ['monsoon-travel-tips'],
        'goa travel', 'Planning your first Goa trip? A practical guide to the seasons, choosing where to stay, and the easiest ways to travel between beaches.', [],
        <<<'HTML'
<p>Goa is small enough to explore in a week, yet varied enough that two visitors can have completely different holidays. Choosing the right season and base makes a big difference to your trip.</p>
<h2>Understanding the seasons</h2>
<p>Most visitors come in the cooler, drier months after the monsoon, when beaches are lively and the sea is calmer. The hottest weeks before the rains are quieter, while the monsoon brings green landscapes, dramatic skies and fewer crowds.</p>
<table><thead><tr><th scope="col">Season</th><th scope="col">What to expect</th><th scope="col">Best for</th></tr></thead><tbody><tr><td>After the monsoon</td><td>Pleasant days, busy beaches</td><td>First-time visitors, beach holidays</td></tr><tr><td>Before the monsoon</td><td>Hot and humid, quieter</td><td>Travellers looking for lower crowds</td></tr><tr><td>Monsoon</td><td>Heavy rain, lush countryside</td><td>Nature lovers, slower travel</td></tr></tbody></table>
<h2>North or south?</h2>
<p>The north is known for busier beaches, markets and nightlife. The south is generally calmer, with longer stretches of quiet sand. Many visitors split their stay, spending a few nights in each area.</p>
<h3>Old towns and inland villages</h3>
<p>Set aside time for the historic quarters, spice farms and riverside villages inland. They show a side of Goa that many beach-only visitors miss.</p>
<h2>Getting around</h2>
<p>Distances look short on a map, but roads can be narrow and journeys take longer than expected. A pre-booked taxi is the most comfortable way to move between beaches, especially with luggage, children or late-evening plans.</p>
<ul>
<li>Agree on the route and pickup time in advance.</li>
<li>Plan day trips around traffic and sunset times.</li>
<li>Keep some cash for small purchases in villages.</li>
</ul>
<h2>Before you go</h2>
<p>Pack light cotton clothing, sun protection and a light rain jacket if you travel near the monsoon. Respect local customs when visiting churches and temples, and enjoy the slower pace that makes Goa special.</p>
HTML,
    ],
    [
        'long-distance-road-trip-tips',
        '10 Tips for a Comfortable Long-Distance Road Trip',
        'Long drives can be relaxing and memorable. These ten practical tips help you plan breaks, pack smart and arrive feeling fresh.',
        'travel-guides', 'arjun-mehta', 'published', -11, false, ['road-trip', 'travel-tips', 'packing', 'safety'], ['outstation-cab-journey-guide'],
        'road trip tips', 'Ten practical tips for long-distance road trips: planning breaks, packing a small comfort kit, staying hydrated and travelling safely.', [],
        <<<'HTML'
<p>A long road journey is a chance to see the landscape change mile by mile. With a little planning it can be as comfortable as any flight, and far more flexible.</p>
<h2>Before you leave</h2>
<ol>
<li><strong>Plan your stops.</strong> Look at the route in advance and choose places for meals and short breaks every two to three hours.</li>
<li><strong>Start early.</strong> Leaving before the morning rush gives you clearer roads and cooler temperatures.</li>
<li><strong>Share your itinerary.</strong> Let someone know your route and expected arrival time.</li>
<li><strong>Pack a comfort kit.</strong> A small bag with water, snacks, tissues, a phone charger and any medicines saves you from digging through suitcases.</li>
</ol>
<h2>On the road</h2>
<ol start="5">
<li><strong>Stay hydrated.</strong> Sip water regularly, especially in hot weather.</li>
<li><strong>Stretch at every stop.</strong> A five-minute walk improves circulation and keeps everyone alert.</li>
<li><strong>Dress in layers.</strong> Air conditioning and changing weather can make temperatures swing during a long journey.</li>
<li><strong>Keep entertainment offline.</strong> Download music, podcasts or films before you leave, as mobile coverage can be patchy.</li>
</ol>
<h2>Arriving well</h2>
<ol start="9">
<li><strong>Do not rush the last stretch.</strong> Fatigue builds up towards the end of a long day, so keep the same steady pace.</li>
<li><strong>Plan your arrival.</strong> Check access to your hotel or destination in advance, especially if you arrive after dark.</li>
</ol>
<blockquote><p>If you are not driving yourself, choose an experienced driver who knows the route and plans regular rest breaks.</p></blockquote>
<p>Most of all, build in some flexibility. The best road trips leave room for an unplanned viewpoint or a roadside meal.</p>
HTML,
    ],
    [
        'outstation-cab-journey-guide',
        'What to Expect From an Outstation Cab Journey',
        'Booking a cab for a trip to another city? Here is how outstation journeys usually work, from planning the route to rest breaks and luggage.',
        'taxi-services', 'editorial-team', 'published', -16, false, ['taxi', 'road-trip', 'travel-tips'], ['long-distance-road-trip-tips', 'airport-taxi-booking-guide'],
        'outstation cab', 'How outstation cab trips work: one-way and round trips, planning the route, breaks, luggage and what to confirm before you travel.', [],
        <<<'HTML'
<p>An outstation cab is a comfortable way to travel between cities without the constraints of fixed timetables. Knowing what to expect helps you plan and avoids surprises on the day.</p>
<h2>One-way or round trip?</h2>
<p>A one-way trip suits travellers who are moving on from their destination by train or flight. A round trip, where the driver stays with you, gives you transport for local sightseeing and the journey home.</p>
<h2>Planning the route</h2>
<p>Discuss the route when you book, including any stops you would like to make along the way. Scenic detours and meal breaks are easy to arrange when they are planned in advance.</p>
<h3>Rest breaks</h3>
<p>On longer journeys, regular breaks keep both passengers and driver fresh. Expect a short stop every few hours and a longer one for meals.</p>
<h2>Luggage and comfort</h2>
<p>Tell the service how many bags you are carrying so the right vehicle is sent. Keep valuables, documents and medicines in a small bag that stays with you in the cabin.</p>
<h2>What to confirm before you travel</h2>
<ul>
<li>Pickup time, address and a contact number for the driver</li>
<li>Whether tolls, parking and interstate permits are included in the quote</li>
<li>For round trips, how the driver's overnight stay is arranged</li>
<li>The plan if your schedule changes on the day</li>
</ul>
<p>With these points clear, an outstation journey becomes a relaxed part of your trip rather than just a way to get there.</p>
HTML,
    ],
    [
        'travelling-with-children-checklist',
        'Travelling With Children: A Practical Checklist',
        'A calm family trip starts with good preparation. Use this checklist to plan transport, packing and breaks when travelling with children.',
        'travel-guides', 'editorial-team', 'published', -21, false, ['family-travel', 'packing', 'safety', 'travel-tips'], ['long-distance-road-trip-tips'],
        'travelling with children', 'A practical checklist for travelling with children: booking the right transport, packing essentials and planning breaks for a calm family trip.', [],
        <<<'HTML'
<p>Family trips create some of the best memories, but they need a little more planning. This checklist covers the essentials so you can spend less time worrying and more time enjoying the journey.</p>
<h2>Booking transport</h2>
<ul>
<li>Request child seats or boosters in advance and mention each child's age.</li>
<li>Choose a vehicle with enough space for prams and extra bags.</li>
<li>Avoid tight connections; children rarely move as fast as the timetable expects.</li>
</ul>
<h2>Packing essentials</h2>
<p>Keep a separate day bag within reach rather than packing everything in the main luggage.</p>
<ul>
<li>Snacks and water bottles</li>
<li>A change of clothes for each child</li>
<li>Wipes, tissues and a small first-aid kit</li>
<li>Favourite toys, books or downloaded shows</li>
<li>Any regular medicines, with written dosage instructions</li>
</ul>
<h2>During the journey</h2>
<p>Plan breaks around naps and mealtimes. A short stop to run around can make the next part of the journey much calmer for everyone.</p>
<h3>Keeping children engaged</h3>
<p>Simple games, such as spotting colours or counting landmarks, keep younger travellers interested. Older children often enjoy following the route on a map.</p>
<h2>Arriving</h2>
<p>Give everyone time to settle in before planning the next activity. A relaxed first evening sets a good tone for the rest of the trip.</p>
HTML,
    ],
    [
        'early-morning-airport-pickup',
        'How to Prepare for an Early-Morning Airport Pickup',
        'Catching a dawn flight? A few simple steps the night before make an early pickup calm, quick and on time.',
        'taxi-services', 'arjun-mehta', 'published', -27, false, ['airport', 'taxi', 'packing'], ['airport-taxi-booking-guide'],
        'early morning airport pickup', 'Simple steps for an early-morning airport pickup: confirm your booking, pack the night before and leave enough time for check-in.', [],
        <<<'HTML'
<p>Early flights are often the cheapest and least crowded, but the alarm clock can make them feel like hard work. Preparing the night before makes the morning almost effortless.</p>
<h2>The evening before</h2>
<ul>
<li>Confirm your pickup time and save your driver's contact number.</li>
<li>Check in online and download your boarding pass.</li>
<li>Pack your bags and leave them by the door.</li>
<li>Lay out travel documents, wallet, keys and phone charger together.</li>
</ul>
<h2>Setting the right pickup time</h2>
<p>Roads are usually clearer before dawn, but do not cut it too fine. Allow time for check-in, bag drop and security, which can be busy even early in the morning.</p>
<h2>In the morning</h2>
<p>Set two alarms and keep breakfast simple. Be ready a few minutes before the pickup time and check your phone for a message from the driver.</p>
<p>With everything prepared, you can relax in the car and arrive at the airport ready for your trip.</p>
HTML,
    ],
    [
        'monsoon-travel-tips',
        'Monsoon Travel: Staying Safe and Comfortable on the Road',
        'Travelling during the rains? Practical advice on planning routes, timing journeys and packing for monsoon weather.',
        'travel-guides', 'editorial-team', 'draft', -1, false, ['monsoon', 'safety', 'travel-tips'], [],
        'monsoon travel', 'Practical monsoon travel advice: checking conditions, allowing extra time, packing for rain and staying safe on the road.', [],
        <<<'HTML'
<p>The monsoon brings beautiful scenery, but heavy rain can slow journeys and change plans. This draft collects advice for travelling safely during the rainy season.</p>
<h2>Check conditions before you leave</h2>
<p>Look at the forecast and local travel advisories on the morning of your journey, and be ready to change your plans if conditions are poor.</p>
<h2>Allow extra time</h2>
<p>Wet roads and reduced visibility mean slower travel. Add a generous buffer, especially before flights and trains.</p>
<h2>Pack for the rain</h2>
<ul>
<li>A compact umbrella and a light rain jacket</li>
<li>Waterproof covers for bags and electronics</li>
<li>Quick-drying clothes and footwear with good grip</li>
</ul>
HTML,
    ],
    [
        'festival-season-travel-planning',
        'Planning Your Travel During the Festival Season',
        'Festival periods are joyful but busy. Book early, plan around peak days and give yourself extra time to travel comfortably.',
        'travel-guides', 'editorial-team', 'scheduled', 7, false, ['festivals', 'travel-tips', 'family-travel'], ['travelling-with-children-checklist'],
        'festival travel', 'How to plan travel during the festival season: booking early, avoiding peak days and allowing extra time on busy roads.', [],
        <<<'HTML'
<p>Festival seasons bring families together, which means busy roads, full trains and high demand for taxis. Planning ahead keeps the celebrations relaxed.</p>
<h2>Book as early as you can</h2>
<p>Transport and accommodation fill up quickly around major festivals. Confirm bookings as soon as your dates are fixed.</p>
<h2>Travel outside the peak days</h2>
<p>If your plans allow, travel a day earlier or later than everyone else. Even a small shift can mean quieter roads and a calmer journey.</p>
<h2>Expect delays</h2>
<p>Processions, closures and heavy traffic are common. Add extra time to every journey and keep snacks and water with you.</p>
HTML,
    ],
];

$out = [];
$out[] = "-- BlogFlow CMS sample content (seed data)";
$out[] = "-- Generated by scripts/make-seed.php — fictional demo content you can edit or delete.";
$out[] = "--";
$out[] = "-- What it adds: 3 categories (one nested), 10 tags, 2 author profiles, 8 posts";
$out[] = "--   (6 published, 1 draft, 1 scheduled 7 days ahead), tags, related posts and media-free content.";
$out[] = "-- What it does NOT touch: users/passwords, settings, theme, menus, existing posts.";
$out[] = "--";
$out[] = "-- HOW TO USE";
$out[] = "--  1. Install BlogFlow first (the installer creates the tables).";
$out[] = "--  2. If you chose a table prefix other than bf_, replace every `bf_ with `yourprefix_ , e.g.";
$out[] = "--       sed 's/`bf_/`cms_/g' seed.sql > seed-cms.sql";
$out[] = "--  3. Import: phpMyAdmin → select the database → Import → choose this file,";
$out[] = "--       or: mysql -u USER -p DATABASE < seed.sql";
$out[] = "--  4. In the admin: Settings → Backup & restore → Maintenance → Clear caches (or wait up to an hour).";
$out[] = "--";
$out[] = "-- Safe to run more than once: rows are matched by slug and existing rows are left unchanged.";
$out[] = "-- Dates are relative to the moment you import. Featured images are not included — add them in";
$out[] = "-- the editor (Media library uploads are files, not database rows).";
$out[] = "";
$out[] = "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci;";
$out[] = "SET time_zone = '+00:00';";
$out[] = "";
$out[] = "-- Categories";
foreach ($categories as $slug => [$name, $desc, $sort, $parent]) {
    if ($parent === null) {
        $out[] = sprintf("INSERT IGNORE INTO %s (parent_id, name, slug, description, robots, sort_order, created_at, updated_at) VALUES (NULL, %s, %s, %s, 'default', %d, UTC_TIMESTAMP(), UTC_TIMESTAMP());",
            t('categories'), q($name), q($slug), q($desc), $sort);
    } else {
        $out[] = sprintf("INSERT IGNORE INTO %s (parent_id, name, slug, description, robots, sort_order, created_at, updated_at) SELECT id, %s, %s, %s, 'default', %d, UTC_TIMESTAMP(), UTC_TIMESTAMP() FROM %s WHERE slug = %s;",
            t('categories'), q($name), q($slug), q($desc), $sort, t('categories'), q($parent));
    }
}
$out[] = "";
$out[] = "-- Tags";
foreach ($tags as $slug => $name) {
    $out[] = sprintf("INSERT IGNORE INTO %s (name, slug, robots, created_at, updated_at) VALUES (%s, %s, 'default', UTC_TIMESTAMP(), UTC_TIMESTAMP());", t('tags'), q($name), q($slug));
}
$out[] = "";
$out[] = "-- Author profiles (public bylines; not login accounts)";
foreach ($authors as $slug => [$name, $title, $bio]) {
    $out[] = sprintf("INSERT IGNORE INTO %s (user_id, name, display_name, slug, job_title, bio, robots, created_at, updated_at) VALUES (NULL, %s, %s, %s, %s, %s, 'default', UTC_TIMESTAMP(), UTC_TIMESTAMP());",
        t('authors'), q($name), q($name), q($slug), q($title), q($bio));
}
$out[] = "";
$out[] = "-- Posts";
foreach ($posts as [$slug, $title, $excerpt, $cat, $author, $status, $days, $featured, $postTags, $related, $kw, $desc, $faq, $html]) {
    $clean = HtmlSanitizer::clean($html);
    $text = HtmlSanitizer::text($clean);
    $words = HtmlSanitizer::wordCount($text);
    $when = $days < 0 ? 'UTC_TIMESTAMP() - INTERVAL ' . (-$days) . ' DAY' : 'UTC_TIMESTAMP() + INTERVAL ' . $days . ' DAY';
    $published = $status === 'draft' ? 'NULL' : $when;
    $modified = $status === 'published' ? $when : 'NULL';
    $out[] = sprintf(
        "INSERT IGNORE INTO %s (title, slug, excerpt, content_html, content_text, status, is_featured, category_id, author_id, published_at, content_modified_at, meta_description, focus_keyword, robots_index, robots_follow, twitter_card, schema_type, schema_enabled, faq, word_count, reading_time, preview_token, created_at, updated_at) VALUES (%s, %s, %s, %s, %s, %s, %d, (SELECT id FROM %s WHERE slug = %s), (SELECT id FROM %s WHERE slug = %s), %s, %s, %s, %s, 'default', 'default', 'default', 'default', 1, %s, %d, %d, MD5(CONCAT(RAND(), %s, UTC_TIMESTAMP(6))), %s, %s);",
        t('posts'), q($title), q($slug), q($excerpt), q($clean), q($text), q($status), $featured ? 1 : 0,
        t('categories'), q($cat), t('authors'), q($author), $published, $modified, q($desc), q($kw),
        $faq ? q(json_encode($faq, JSON_UNESCAPED_UNICODE)) : 'NULL', $words, HtmlSanitizer::readingTime($words), q($slug),
        $days < 0 ? $when : 'UTC_TIMESTAMP()', $days < 0 ? $when : 'UTC_TIMESTAMP()'
    );
}
$out[] = "";
$out[] = "-- Post tags";
foreach ($posts as [$slug, , , , , , , , $postTags]) {
    $out[] = sprintf("INSERT IGNORE INTO %s (post_id, tag_id) SELECT p.id, t.id FROM %s p INNER JOIN %s t ON t.slug IN (%s) WHERE p.slug = %s;",
        t('post_tags'), t('posts'), t('tags'), implode(', ', array_map('q', $postTags)), q($slug));
}
$out[] = "";
$out[] = "-- Related posts (shown first under each article)";
foreach ($posts as [$slug, , , , , , , , , $related]) {
    foreach ($related as $i => $rel) {
        $out[] = sprintf("INSERT IGNORE INTO %s (post_id, related_id, sort_order) SELECT p.id, r.id, %d FROM %s p INNER JOIN %s r ON r.slug = %s WHERE p.slug = %s;",
            t('post_related'), $i, t('posts'), t('posts'), q($rel), q($slug));
    }
}
$out[] = "";

file_put_contents(BF_ROOT . '/database/seed.sql', implode("\n", $out));
echo 'Wrote blogs/database/seed.sql (' . count($posts) . " posts)\n";
