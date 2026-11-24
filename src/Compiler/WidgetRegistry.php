<?php
/**
 * Mega Builder — Full 7-Category, 77-Widget Library & Starter Blueprints Registry
 * Modeled after Creative Elements (docs.webshopworks.com/creative-elements)
 */

declare(strict_types=1);

namespace MegaBuilder\Compiler;

final class WidgetRegistry
{
    /**
     * Return all 77 widgets organized across the 7 Creative Elements categories, each tagged with
     * 'status': 'ready' when it has a storefront renderer, 'coming_soon' when it does not yet.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function getAllWidgets(): array
    {
        return array_map(
            static fn(array $w): array => $w + ['status' => LayoutCompiler::isRendered((string) $w['type']) ? 'ready' : 'coming_soon'],
            self::widgetDefinitions()
        );
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private static function widgetDefinitions(): array
    {
        return [
            // =====================================================================
            // CATEGORY 1: BASIC & LAYOUT WIDGETS (11)
            // =====================================================================
            ['type' => 'flex_container',  'category' => 'basic', 'label' => 'Flex / Grid Container', 'icon' => 'layout-grid',   'defaults' => ['heading' => 'Container Block', 'subtitle' => 'Responsive Flex & CSS Grid layout wrapper']],
            ['type' => 'inner_section',   'category' => 'basic', 'label' => 'Inner Section / Columns','icon' => 'columns',       'defaults' => ['heading' => 'Multi-Column Row', 'columns' => 2]],
            ['type' => 'heading',         'category' => 'basic', 'label' => 'Heading',               'icon' => 'type',          'defaults' => ['tag' => 'h2', 'text' => 'Crafted for Modern Living', 'subtitle' => 'SIGNATURE COLLECTION']],
            ['type' => 'text_editor',     'category' => 'basic', 'label' => 'Text Editor',           'icon' => 'align-left',    'defaults' => ['html' => '<p>Discover timeless architectural furniture crafted from sustainably sourced solid oak, Italian bouclé, and brushed brass accents.</p>']],
            ['type' => 'image',           'category' => 'basic', 'label' => 'Image',                 'icon' => 'image',         'defaults' => ['src' => 'https://images.unsplash.com/photo-1555041469-a586c61ea9bc?auto=format&fit=crop&w=1000&q=80', 'alt' => 'Designer Sofa Showcase', 'caption' => 'Artisan Oak & Linen Sofa']],
            ['type' => 'video',           'category' => 'basic', 'label' => 'Video Player',          'icon' => 'video',         'defaults' => ['video_url' => 'https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ', 'poster_label' => 'Watch Studio Craftsmanship Film']],
            ['type' => 'button',          'category' => 'basic', 'label' => 'Button CTA',            'icon' => 'mouse-pointer', 'defaults' => ['text' => 'Explore Collection', 'url' => '#catalog', 'variant' => 'primary']],
            ['type' => 'divider',         'category' => 'basic', 'label' => 'Divider Line',          'icon' => 'minus',         'defaults' => ['style_type' => 'solid', 'thickness' => 1]],
            ['type' => 'spacer',          'category' => 'basic', 'label' => 'Vertical Spacer',       'icon' => 'move-vertical', 'defaults' => ['height_px' => 32]],
            ['type' => 'icon',            'category' => 'basic', 'label' => 'Vector Icon Badge',     'icon' => 'sparkles',      'defaults' => ['icon_name' => 'shield-check', 'text' => '10-Year Structural Warranty']],
            ['type' => 'google_maps',     'category' => 'basic', 'label' => 'Google Maps Showroom',  'icon' => 'map-pin',       'defaults' => ['location' => 'Miami Design District Showroom', 'zoom' => 14, 'address' => '3841 NE 2nd Ave, Miami, FL']],

            // =====================================================================
            // CATEGORY 2: GENERAL CONTENT WIDGETS (15)
            // =====================================================================
            ['type' => 'accordion',       'category' => 'general', 'label' => 'Accordion FAQ',         'icon' => 'chevrons-down', 'defaults' => ['title' => 'Care & Delivery Specifications', 'items' => [
                ['title' => 'White-Glove In-Home Delivery & Assembly', 'answer' => 'Every flagship sofa and dining table includes scheduled room-of-choice placement and packaging removal.'],
                ['title' => 'FSC-Certified Solid Kiln-Dried Hardwood', 'answer' => 'Our frames use sustainably harvested European oak and walnut reinforced with mortise-and-tenon joinery.'],
                ['title' => '30-Day Showroom Comfort Guarantee', 'answer' => 'Experience your piece at home for 30 days with complimentary exchanges or returns.'],
            ]]],
            ['type' => 'toggle',          'category' => 'general', 'label' => 'Collapsible Toggle',    'icon' => 'list-collapse', 'defaults' => ['title' => 'Technical Dimensions & Clearance', 'content' => 'Width: 240 cm × Depth: 98 cm × Seat Height: 43 cm. Minimum doorway clearance: 82 cm.']],
            ['type' => 'tabs',            'category' => 'general', 'label' => 'Interactive Tabs',      'icon' => 'folder-kanban', 'defaults' => ['items' => [
                ['title' => 'Materials & Craft', 'content' => 'Full-grain aniline leather and high-resilience feather-wrapped foam cores.'],
                ['title' => 'Dimensions',        'content' => 'Available in 3-Seater (220cm), Grand 4-Seater (260cm), and Modular L-Sectional.'],
                ['title' => 'Care Guide',        'content' => 'Dust weekly with a dry microfiber cloth; blot spills immediately without harsh solvents.'],
            ]]],
            ['type' => 'alert',           'category' => 'general', 'label' => 'Alert & Notice Banner', 'icon' => 'bell',          'defaults' => ['badge' => 'LIMITED EVENT', 'text' => 'Complimentary White-Glove Delivery on all Living & Dining orders over $999.']],
            ['type' => 'counter',         'category' => 'general', 'label' => 'Animated Counter',      'icon' => 'hash',          'defaults' => ['number' => '18,500+', 'label' => 'Homes Furnished Worldwide', 'prefix' => '', 'suffix' => '']],
            ['type' => 'progress_bar',    'category' => 'general', 'label' => 'Progress / Stock Meter','icon' => 'bar-chart-2',   'defaults' => ['label' => 'Artisan Batch Availability — 82% Reserved', 'percentage' => 82]],
            ['type' => 'icon_box',        'category' => 'general', 'label' => 'Icon Feature Box',      'icon' => 'box',           'defaults' => ['icon_name' => 'truck', 'title' => 'Express Showroom Dispatch', 'text' => 'In-stock heirloom furniture ships within 48 hours from our regional fulfillment hub.']],
            ['type' => 'icon_list',       'category' => 'general', 'label' => 'Icon Feature List',     'icon' => 'list-checks',   'defaults' => ['items' => [
                ['title' => 'Stain-Resistant Performance Bouclé & Linen'],
                ['title' => 'Custom Modular Configurations Available'],
                ['title' => 'Complimentary Fabric Swatch Kit by Mail'],
            ]]],
            ['type' => 'image_box',       'category' => 'general', 'label' => 'Image Feature Card',    'icon' => 'layout',        'defaults' => ['src' => 'https://images.unsplash.com/photo-1586023492125-27b2c045efd7?auto=format&fit=crop&w=800&q=80', 'title' => 'Scandinavian Dining Room', 'text' => 'Warm oak silhouettes designed for memorable gatherings.', 'url' => '#dining']],
            ['type' => 'image_carousel',  'category' => 'general', 'label' => 'Image Carousel',        'icon' => 'gallery-horizontal', 'defaults' => ['title' => 'Lookbook Gallery', 'items' => [
                ['title' => 'Minimalist Loft Living', 'src' => 'https://images.unsplash.com/photo-1555041469-a586c61ea9bc?auto=format&fit=crop&w=600&q=80'],
                ['title' => 'Organic Oak Dining',     'src' => 'https://images.unsplash.com/photo-1615873968403-89e068629265?auto=format&fit=crop&w=600&q=80'],
                ['title' => 'Serene Master Suite',    'src' => 'https://images.unsplash.com/photo-1505693416388-ac5ce068fe85?auto=format&fit=crop&w=600&q=80'],
            ]]],
            ['type' => 'image_gallery',   'category' => 'general', 'label' => 'Masonry Image Gallery', 'icon' => 'grid',          'defaults' => ['title' => 'Client Interior Showcase', 'columns' => 3]],
            ['type' => 'social_icons',    'category' => 'general', 'label' => 'Social Media Icons',    'icon' => 'share-2',       'defaults' => ['networks' => 'Instagram • Pinterest • Houzz • YouTube']],
            ['type' => 'star_rating',     'category' => 'general', 'label' => 'Star Rating Badge',     'icon' => 'star',          'defaults' => ['rating' => '4.9', 'reviews_count' => '1,420 Verified Buyers', 'caption' => 'Rated Excellent on Trustpilot']],
            ['type' => 'testimonial',     'category' => 'general', 'label' => 'Customer Testimonial',  'icon' => 'quote',         'defaults' => ['quote' => '“The build quality of our DoIt Furniture sectional rivals designer pieces costing three times as much.”', 'author' => 'Elena Rostova — Interior Architect, NY', 'rating' => 5]],
            ['type' => 'custom_html',     'category' => 'general', 'label' => 'Custom HTML / Shortcode','icon' => 'code',         'defaults' => ['html' => '<div class="mb-callout-chip">Custom HTML &amp; Dynamic Tag Support: <strong>{{mb:shop_name}}</strong></div>']],

            // =====================================================================
            // CATEGORY 3: PREMIUM & CONVERSION WIDGETS (14)
            // =====================================================================
            ['type' => 'creative_slider', 'category' => 'premium', 'label' => 'Creative Hero Slider',  'icon' => 'sliders',       'defaults' => ['autoplay_ms' => 5000, 'items' => [
                ['badge' => 'AUTUMN / WINTER ATELIER', 'title' => 'Architectural Comfort for Modern Homes', 'subtitle' => 'Up to 30% off handcrafted modular sectionals & solid walnut dining tables.', 'cta_text' => 'Shop Living Room', 'cta_url' => '#living', 'src' => 'https://images.unsplash.com/photo-1555041469-a586c61ea9bc?auto=format&fit=crop&w=1200&q=80'],
                ['badge' => 'NEW ARRIVALS',            'title' => 'The Nordic Solid Oak Dining Series',     'subtitle' => 'Sustainably harvested timber finished with natural organic oil.',               'cta_text' => 'Explore Dining',   'cta_url' => '#dining', 'src' => 'https://images.unsplash.com/photo-1615873968403-89e068629265?auto=format&fit=crop&w=1200&q=80'],
                ['badge' => 'BESPOKE BEDROOM',         'title' => 'Sanctuary Upholstered Bed Frames',       'subtitle' => 'Engineered acoustic headboards wrapped in Belgian bouclé.',                     'cta_text' => 'Discover Bedroom', 'cta_url' => '#bedroom','src' => 'https://images.unsplash.com/photo-1505693416388-ac5ce068fe85?auto=format&fit=crop&w=1200&q=80'],
            ]]],
            ['type' => 'animated_headline','category' => 'premium','label' => 'Animated Headline',     'icon' => 'wand-2',        'defaults' => ['before_text' => 'Furniture Designed for', 'highlight_text' => 'Timeless Living', 'after_text' => '& Everyday Joy']],
            ['type' => 'call_to_action',  'category' => 'premium', 'label' => 'Call To Action Card',   'icon' => 'megaphone',     'defaults' => ['badge' => 'TRADE & INTERIOR DESIGN PROGRAM', 'title' => 'Book a Complimentary 1-on-1 Design Consultation', 'text' => 'Work with our in-house spatial planners to configure your floor plan and custom upholstery.', 'cta_text' => 'Schedule Free Session', 'cta_url' => '#consult']],
            ['type' => 'countdown',       'category' => 'premium', 'label' => 'Countdown Timer',       'icon' => 'timer',         'defaults' => ['title' => 'Seasonal Showroom Event Ends In:', 'days' => 4, 'hours' => 18, 'minutes' => 42, 'seconds' => 15]],
            ['type' => 'flip_box',        'category' => 'premium', 'label' => '3D Interactive Flip Box','icon' => 'refresh-cw',    'defaults' => ['front_title' => 'Request Free Fabric Swatches', 'front_desc' => 'Hover or tap to see what is included in your complimentary designer kit.', 'back_title' => '6 Curated Textiles Shipped Free', 'back_desc' => 'Receive bouclé, performance velvet, Belgian linen, and oak wood finish samples in 3 days.', 'cta_text' => 'Order Swatch Box']],
            ['type' => 'image_hotspot',   'category' => 'premium', 'label' => 'Image Hotspot (Shop Look)','icon' => 'crosshair',   'defaults' => ['title' => 'Shop The Look — Milan Living Room', 'src' => 'https://images.unsplash.com/photo-1555041469-a586c61ea9bc?auto=format&fit=crop&w=1100&q=80', 'items' => [
                ['title' => 'Verona Cloud Modular Sofa — $1,890', 'x' => 42, 'y' => 58, 'url' => '#product-1'],
                ['title' => 'Travertine Stone Plinth Table — $640', 'x' => 65, 'y' => 76, 'url' => '#product-2'],
                ['title' => 'Brushed Brass Arc Floor Lamp — $320', 'x' => 20, 'y' => 34, 'url' => '#product-3'],
            ]]],
            ['type' => 'contact_form',    'category' => 'premium', 'label' => 'Concierge Contact Form','icon' => 'mail',          'defaults' => ['title' => 'Ask Our Furniture Concierge', 'subtitle' => 'Questions about custom dimensions, trade pricing, or delivery lead times? We reply within 2 business hours.', 'button_text' => 'Send Inquiry']],
            ['type' => 'email_subscription','category' => 'premium','label' => 'Newsletter Subscription','icon' => 'send',         'defaults' => ['title' => 'Join The Private Design List', 'subtitle' => 'Receive $100 toward your first order plus early access to seasonal lookbooks.', 'placeholder' => 'Enter your email address...', 'button_text' => 'Claim $100 Voucher']],
            ['type' => 'testimonial_carousel','category' => 'premium','label' => 'Testimonial Carousel','icon' => 'message-square','defaults' => ['title' => 'Loved by Homeowners & Interior Designers', 'items' => [
                ['title' => '“Flawless delivery and extraordinary upholstery quality.” — Marcus V., Miami'],
                ['title' => '“Our dining table is the centerpiece of our home.” — Sophia L., Austin'],
                ['title' => '“We specify DoIt Furniture for all our boutique staging projects.” — Studio K Architecture'],
            ]]],
            ['type' => 'articles_grid',   'category' => 'premium', 'label' => 'Editorial & Guides Grid','icon' => 'book-open',     'defaults' => ['title' => 'The Interior Design Journal', 'items' => [
                ['title' => 'How to Choose the Right Sectional Scale for Open-Concept Living', 'answer' => '5 min read • Spatial Planning Guide'],
                ['title' => 'Solid Oak vs. Walnut: Caring for Natural Hardwood Finishes',      'answer' => '4 min read • Materials & Care'],
                ['title' => '2026 Color Trends: Warm Terracotta, Sage & Raw Bouclé',           'answer' => '6 min read • Seasonal Lookbook'],
            ]]],
            ['type' => 'ps_module_widget','category' => 'premium', 'label' => 'PrestaShop Module / Hook','icon' => 'plug',        'defaults' => ['module_name' => 'ps_featuredproducts', 'hook_name' => 'displayHome', 'title' => 'Native PrestaShop Module Embed']],
            ['type' => 'template_widget', 'category' => 'premium', 'label' => 'Saved Template / Sub-Studio','icon' => 'layers',   'defaults' => ['template_id' => 0, 'component_kind' => 'slider', 'title' => 'Embedded Sub-Studio Component']],
            ['type' => 'trust_badges',    'category' => 'premium', 'label' => 'Trust & Reassurance Bar','icon' => 'award',        'defaults' => ['items' => [
                ['title' => 'Free White-Glove Delivery', 'answer' => 'On orders over $999'],
                ['title' => '10-Year Frame Warranty',    'answer' => 'Kiln-dried hardwood guarantee'],
                ['title' => '0% APR Financing',          'answer' => 'Pay over 12 months'],
                ['title' => '30-Day Home Trial',         'answer' => 'Easy hassle-free returns'],
            ]]],
            ['type' => 'lightbox_trigger','category' => 'premium', 'label' => 'Modal / Lightbox Popup','icon' => 'maximize-2',    'defaults' => ['button_text' => 'View Room Size & Dimension Guide', 'modal_title' => 'Interactive Measuring Guide', 'content' => 'Measure doorway width, diagonal clearance, and stairwell turns before delivery.']],

            // =====================================================================
            // CATEGORY 4: SITE, HEADER & FOOTER WIDGETS (10)
            // =====================================================================
            ['type' => 'shop_logo',       'category' => 'site', 'label' => 'Shop Brand Logo',          'icon' => 'home',          'defaults' => ['text' => '{{mb:shop_name}}', 'tagline' => 'FINE ARCHITECTURAL FURNITURE']],
            ['type' => 'shop_title',      'category' => 'site', 'label' => 'Dynamic Shop Title',       'icon' => 'bookmark',      'defaults' => ['text' => '{{mb:shop_name}}']],
            ['type' => 'nav_megamenu',    'category' => 'site', 'label' => 'Nav Menu / MegaMenu',      'icon' => 'menu',          'defaults' => ['source' => 'categories', 'max_items' => 8, 'menu_label' => 'Menu', 'items' => [
                ['title' => 'Living Room', 'url' => '#', 'badge' => 'NEW', 'columns' => [
                    ['heading' => 'Seating', 'url' => '#', 'links' => [['title' => 'Sofas', 'url' => '#'], ['title' => 'Sectionals', 'url' => '#'], ['title' => 'Lounge Chairs', 'url' => '#']]],
                    ['heading' => 'Tables', 'url' => '#', 'links' => [['title' => 'Coffee Tables', 'url' => '#'], ['title' => 'Side Tables', 'url' => '#']]],
                ], 'promo_image' => '', 'promo_title' => '30% off modular sofas', 'promo_url' => '#'],
                ['title' => 'Dining', 'url' => '#', 'badge' => '', 'columns' => [
                    ['heading' => 'Dining', 'url' => '#', 'links' => [['title' => 'Dining Tables', 'url' => '#'], ['title' => 'Dining Chairs', 'url' => '#'], ['title' => 'Sideboards', 'url' => '#']]],
                ]],
                ['title' => 'Bedroom', 'url' => '#', 'badge' => '', 'columns' => []],
            ]]],
            ['type' => 'ajax_search',     'category' => 'site', 'label' => 'AJAX Live Search Bar',     'icon' => 'search',        'defaults' => ['placeholder' => 'Search sofas, oak dining tables, bouclé chairs, SKU...']],
            ['type' => 'shopping_cart',   'category' => 'site', 'label' => 'Shopping Cart Trigger',    'icon' => 'shopping-bag',  'defaults' => ['label' => 'Bag', 'count_tag' => '{{mb:cart_count}}']],
            ['type' => 'user_menu',       'category' => 'site', 'label' => 'Customer Account Menu',    'icon' => 'user',          'defaults' => ['label' => 'My Account & Orders']],
            ['type' => 'language_selector','category' => 'site','label' => 'Language Selector',        'icon' => 'globe',         'defaults' => ['current' => 'EN', 'languages' => 'EN | ES | FR']],
            ['type' => 'currency_selector','category' => 'site','label' => 'Currency Selector',        'icon' => 'dollar-sign',   'defaults' => ['current' => 'USD $', 'currencies' => 'USD $ | EUR € | GBP £']],
            ['type' => 'breadcrumb',      'category' => 'site', 'label' => 'Breadcrumb Navigation',    'icon' => 'chevron-right', 'defaults' => ['trail' => 'Home  /  {{mb:category_name}}  /  {{mb:product_name}}']],
            ['type' => 'page_title',      'category' => 'site', 'label' => 'Dynamic Page Title Banner','icon' => 'heading',       'defaults' => ['title' => '{{mb:page_title}}', 'subtitle' => 'Curated Designer Collections']],

            // =====================================================================
            // CATEGORY 5: CATALOG & LISTINGS WIDGETS (6)
            // =====================================================================
            ['type' => 'product_grid',    'category' => 'catalog', 'label' => 'Dynamic Product Grid',     'icon' => 'layout-grid', 'defaults' => ['title' => 'Bestselling Architectural Pieces', 'source' => 'featured', 'limit' => 4, 'columns' => 4]],
            ['type' => 'product_carousel','category' => 'catalog', 'label' => 'Product Carousel Slider',  'icon' => 'GalleryHorizontalEnd', 'defaults' => ['title' => 'New Showroom Arrivals', 'source' => 'new_products', 'limit' => 4]],
            ['type' => 'product_box',     'category' => 'catalog', 'label' => 'Single Featured Product',  'icon' => 'package',     'defaults' => ['badge' => 'EDITOR’S PICK', 'title' => 'Milano Bouclé Curved Sofa', 'price' => '$1,890.00', 'old_price' => '$2,390.00', 'src' => 'https://images.unsplash.com/photo-1555041469-a586c61ea9bc?auto=format&fit=crop&w=800&q=80']],
            ['type' => 'category_grid',   'category' => 'catalog', 'label' => 'Visual Category Grid',     'icon' => 'grid',        'defaults' => ['title' => 'Shop by Room & Department', 'items' => [
                ['title' => 'Living Room Sanctuary', 'answer' => '48 Pieces', 'src' => 'https://images.unsplash.com/photo-1555041469-a586c61ea9bc?auto=format&fit=crop&w=600&q=80', 'url' => '/2-home'],
                ['title' => 'Dining & Entertaining', 'answer' => '36 Pieces', 'src' => 'https://images.unsplash.com/photo-1615873968403-89e068629265?auto=format&fit=crop&w=600&q=80', 'url' => '/2-home'],
                ['title' => 'Bedroom Retreats',      'answer' => '29 Pieces', 'src' => 'https://images.unsplash.com/photo-1505693416388-ac5ce068fe85?auto=format&fit=crop&w=600&q=80', 'url' => '/2-home'],
                ['title' => 'Workspace & Study',     'answer' => '22 Pieces', 'src' => 'https://images.unsplash.com/photo-1518455027359-f3f8164ba6bd?auto=format&fit=crop&w=600&q=80', 'url' => '/2-home'],
            ]]],
            ['type' => 'category_list',   'category' => 'catalog', 'label' => 'Category Pill Strip',      'icon' => 'list',        'defaults' => ['items' => [
                ['title' => 'Modular Sectionals'],
                ['title' => 'Solid Oak Tables'],
                ['title' => 'Bouclé Accent Chairs'],
                ['title' => 'Media Consoles'],
                ['title' => 'King Bed Frames'],
            ]]],
            ['type' => 'category_tree',   'category' => 'catalog', 'label' => 'Category Hierarchy Tree',  'icon' => 'git-branch',  'defaults' => ['title' => 'Browse Departments']],

            // =====================================================================
            // CATEGORY 6: PRODUCT DETAIL PAGE WIDGETS (10)
            // =====================================================================
            ['type' => 'product_title',       'category' => 'product', 'label' => 'Product Title & Brand',      'icon' => 'tag',           'defaults' => ['text' => '{{mb:product_name}}', 'brand' => 'DOIT ATELIER COLLECTION']],
            ['type' => 'product_price',       'category' => 'product', 'label' => 'Product Price & Tax Badge',  'icon' => 'credit-card',   'defaults' => ['price' => '{{mb:product_price}}', 'badge' => 'SAVE 20% • IN STOCK']],
            ['type' => 'product_images',      'category' => 'product', 'label' => 'Product Zoom Gallery',       'icon' => 'camera',        'defaults' => ['src' => 'https://images.unsplash.com/photo-1555041469-a586c61ea9bc?auto=format&fit=crop&w=1000&q=80', 'enable_zoom' => true]],
            ['type' => 'add_to_cart',         'category' => 'product', 'label' => 'Add to Cart & Buy Bar',      'icon' => 'shopping-cart', 'defaults' => ['button_text' => 'Add to Showroom Bag', 'secondary_text' => 'Order Free Fabric Swatch']],
            ['type' => 'product_description', 'category' => 'product', 'label' => 'Product Description Block',  'icon' => 'file-text',     'defaults' => ['text' => '{{mb:product_description}}']],
            ['type' => 'product_stock',       'category' => 'product', 'label' => 'Live Stock & Dispatch Timer','icon' => 'check-circle',  'defaults' => ['status' => 'In Stock — Ready for White-Glove Dispatch in 48h']],
            ['type' => 'product_variations',  'category' => 'product', 'label' => 'Upholstery & Finish Swatches','icon' => 'palette',      'defaults' => ['label' => 'Select Upholstery & Wood Finish', 'swatches' => 'Alabaster Bouclé • Warm Cognac Leather • Sage Velvet • Natural Smoked Oak']],
            ['type' => 'product_rating',      'category' => 'product', 'label' => 'Verified Buyer Reviews',     'icon' => 'thumbs-up',     'defaults' => ['score' => '4.9 / 5.0', 'count' => '128 Verified Showroom Reviews']],
            ['type' => 'product_meta',        'category' => 'product', 'label' => 'Product SKU / Specs Table',  'icon' => 'info',          'defaults' => ['sku' => '{{mb:product_reference}}', 'dimensions' => '240cm W × 98cm D × 78cm H', 'materials' => 'FSC Solid Oak + Belgian Performance Bouclé']],
            ['type' => 'product_share',       'category' => 'product', 'label' => 'Product Share & Save Wishlist','icon' => 'heart',       'defaults' => ['label' => 'Save to Moodboard or Share with Designer']],

            // =====================================================================
            // CATEGORY 7: CATEGORY LISTING & CUSTOMER ACCOUNT WIDGETS (11)
            // =====================================================================
            ['type' => 'faceted_filters',     'category' => 'listing_customer', 'label' => 'Faceted Search Filters',   'icon' => 'filter',        'defaults' => ['title' => 'Filter by Room, Material, Finish & Price']],
            ['type' => 'active_filters',      'category' => 'listing_customer', 'label' => 'Active Filter Chips Bar',  'icon' => 'x-circle',      'defaults' => ['chips' => 'Material: Solid Oak ×   |   In Stock Ready to Ship ×   |   Clear All']],
            ['type' => 'category_description','category' => 'listing_customer', 'label' => 'Category Hero Description','icon' => 'align-justify', 'defaults' => ['title' => '{{mb:category_name}}', 'text' => 'Explore heirloom-grade pieces crafted for modern architectural spaces.']],
            ['type' => 'category_seo_text',   'category' => 'listing_customer', 'label' => 'Category Bottom SEO Guide','icon' => 'file-search',   'defaults' => ['title' => 'Buying Guide & Material Craftsmanship', 'text' => 'Every piece in our collection undergoes a 24-point artisan inspection prior to white-glove dispatch.']],
            ['type' => 'listing_sort',        'category' => 'listing_customer', 'label' => 'Catalog Sort Dropdown',    'icon' => 'arrow-up-down', 'defaults' => ['options' => 'Featured • Newest • Price: Low to High • Best Selling']],
            ['type' => 'listing_pagination',  'category' => 'listing_customer', 'label' => 'Catalog Pagination Bar',   'icon' => 'more-horizontal','defaults' => ['pages' => '1  2  3  4  Next →']],
            ['type' => 'listing_info',        'category' => 'listing_customer', 'label' => 'Product Count Summary',    'icon' => 'list-ordered',  'defaults' => ['text' => 'Showing 1–16 of 64 Handcrafted Pieces']],
            ['type' => 'customer_login',      'category' => 'listing_customer', 'label' => 'Customer Sign-In Form',    'icon' => 'log-in',        'defaults' => ['title' => 'Sign In to Your DoIt Furniture Account', 'button_text' => 'Sign In Securely']],
            ['type' => 'customer_register',   'category' => 'listing_customer', 'label' => 'Customer Registration Form','icon' => 'user-plus',    'defaults' => ['title' => 'Create a Member or Trade Account', 'button_text' => 'Create Account']],
            ['type' => 'password_recovery',   'category' => 'listing_customer', 'label' => 'Password Reset Card',      'icon' => 'key',           'defaults' => ['title' => 'Reset Your Account Password', 'button_text' => 'Send Recovery Link']],
            ['type' => 'account_nav_links',   'category' => 'listing_customer', 'label' => 'Customer Portal Quick Grid','icon' => 'compass',      'defaults' => ['items' => [
                ['title' => 'Order Tracking & White-Glove Delivery Schedule'],
                ['title' => 'Saved Room Moodboards & Wishlists'],
                ['title' => 'Addresses, Tax Invoices & Vouchers'],
            ]]],
        ];
    }

    /**
     * Get widget definition by type.
     *
     * @return array<string, mixed>
     */
    public static function getWidget(string $type): array
    {
        foreach (self::getAllWidgets() as $w) {
            if ($w['type'] === $type) {
                return $w;
            }
        }
        return [
            'type'     => $type,
            'category' => 'basic',
            'label'    => ucfirst(str_replace('_', ' ', $type)),
            'icon'     => 'box',
            'defaults' => ['text' => ucfirst(str_replace('_', ' ', $type))],
        ];
    }

    /**
     * Return 1-Click Starter Blueprints for all 15 Page Sections + 4 Component Sub-Studios.
     *
     * @return array<string, array<string, mixed>>
     */
    public static function getStarterBlueprints(): array
    {
        return [
            // 4 Sub-Studio Blueprints
            'megamenu' => [
                'entity_kind'  => 'sub_component',
                'section_type' => 'megamenu',
                'title'        => '[Starter] Flagship Architectural MegaMenu',
                'hook_name'    => 'displayNavFullWidth',
                'layout'       => self::buildSingleSectionLayout('Flagship Department MegaMenu', [
                    ['width' => 100, 'widgets' => [
                        self::makeWidget('nav_megamenu', 'Department MegaMenu'),
                    ]],
                ]),
            ],
            'slider' => [
                'entity_kind'  => 'sub_component',
                'section_type' => 'slider',
                'title'        => '[Starter] 3-Slide Atelier Hero Slider',
                'hook_name'    => 'displayHome',
                'layout'       => self::buildSingleSectionLayout('Flagship Hero Slider', [
                    ['width' => 100, 'widgets' => [
                        self::makeWidget('creative_slider', 'Flagship Hero Slider'),
                    ]],
                ]),
            ],
            'card' => [
                'entity_kind'  => 'sub_component',
                'section_type' => 'card',
                'title'        => '[Starter] Bespoke Product Miniature Card',
                'hook_name'    => 'displayHome',
                'layout'       => self::buildSingleSectionLayout('Bespoke Product & Category Cards', [
                    ['width' => 50, 'widgets' => [self::makeWidget('product_box', 'Bespoke Product Card')]],
                    ['width' => 50, 'widgets' => [self::makeWidget('image_box', 'Category Showcase Card')]],
                ]),
            ],
            'accordion' => [
                'entity_kind'  => 'sub_component',
                'section_type' => 'accordion',
                'title'        => '[Starter] White-Glove Delivery & Care FAQ Accordion',
                'hook_name'    => 'displayFooterProduct',
                'layout'       => self::buildSingleSectionLayout('Storefront FAQ Accordion', [
                    ['width' => 100, 'widgets' => [
                        self::makeWidget('accordion', 'Delivery & Care FAQ Accordion'),
                    ]],
                ]),
            ],

            // 15 Page Template Section Blueprints
            'header' => [
                'entity_kind'  => 'page_template',
                'section_type' => 'header',
                'title'        => 'Flagship Storefront Header & MegaMenu Bar',
                'hook_name'    => 'displayNavFullWidth',
                'layout'       => [
                    'version'  => '1.0',
                    'sections' => [
                        self::makeSection('Top Announcement Bar', ['padding_y' => 8, 'bg_color' => '#0f172a'], [
                            ['width' => 100, 'widgets' => [
                                self::makeWidget('alert', 'Announcement Banner', [], ['text_color' => '#f8fafc', 'bg_color' => '#0f172a', 'text_align' => 'center']),
                            ]],
                        ]),
                        self::makeSection('Main Navigation & Utility Header', ['padding_y' => 16, 'bg_color' => '#ffffff'], [
                            ['width' => 25, 'widgets' => [self::makeWidget('shop_logo', 'Brand Logo')]],
                            ['width' => 45, 'widgets' => [self::makeWidget('ajax_search', 'Live Search Bar')]],
                            ['width' => 30, 'widgets' => [self::makeWidget('shopping_cart', 'Cart & Account Trigger')]],
                        ]),
                        self::makeSection('Department MegaMenu Strip', ['padding_y' => 10, 'bg_color' => '#f8fafc'], [
                            ['width' => 100, 'widgets' => [self::makeWidget('nav_megamenu', 'Primary MegaMenu')]],
                        ]),
                    ],
                ],
            ],
            'homepage' => [
                'entity_kind'  => 'page_template',
                'section_type' => 'homepage',
                'title'        => 'Flagship Architectural Furniture Homepage',
                'hook_name'    => 'displayHome',
                'layout'       => [
                    'version'  => '1.0',
                    'sections' => [
                        self::makeSection('Hero Showcase Slider', ['padding_y' => 24], [
                            ['width' => 100, 'widgets' => [self::makeWidget('creative_slider', 'Flagship Hero Slider')]],
                        ]),
                        self::makeSection('Reassurance & Trust Pillars', ['padding_y' => 20, 'bg_color' => '#f8fafc'], [
                            ['width' => 100, 'widgets' => [self::makeWidget('trust_badges', 'Showroom Guarantees')]],
                        ]),
                        self::makeSection('Shop by Room Categories', ['padding_y' => 40], [
                            ['width' => 100, 'widgets' => [self::makeWidget('category_grid', 'Room Collections Grid')]],
                        ]),
                        self::makeSection('Interactive Shop The Look', ['padding_y' => 40, 'bg_color' => '#f8fafc'], [
                            ['width' => 100, 'widgets' => [self::makeWidget('image_hotspot', 'Shop The Look Living Room')]],
                        ]),
                        self::makeSection('Bestselling Catalog Showcase', ['padding_y' => 40], [
                            ['width' => 100, 'widgets' => [self::makeWidget('product_grid', 'Bestselling Furniture Grid')]],
                        ]),
                        self::makeSection('Client Testimonials & Newsletter', ['padding_y' => 40, 'bg_color' => '#f8fafc'], [
                            ['width' => 50, 'widgets' => [self::makeWidget('testimonial', 'Architect Testimonial')]],
                            ['width' => 50, 'widgets' => [self::makeWidget('email_subscription', 'VIP Design List Signup')]],
                        ]),
                    ],
                ],
            ],
            'product' => [
                'entity_kind'  => 'page_template',
                'section_type' => 'product',
                'title'        => 'Bespoke Product Detail & Craftsmanship Showcase',
                'hook_name'    => 'displayFooterProduct',
                'layout'       => [
                    'version'  => '1.0',
                    'sections' => [
                        self::makeSection('Product Hero Gallery & Buy Box', ['padding_y' => 32], [
                            ['width' => 55, 'widgets' => [self::makeWidget('product_images', 'Product Zoom Gallery')]],
                            ['width' => 45, 'widgets' => [
                                self::makeWidget('product_title', 'Product Title'),
                                self::makeWidget('product_price', 'Dynamic Price Badge'),
                                self::makeWidget('product_variations', 'Upholstery & Wood Swatches'),
                                self::makeWidget('product_stock', 'Dispatch Readiness'),
                                self::makeWidget('add_to_cart', 'Add to Bag CTA'),
                                self::makeWidget('product_meta', 'Dimensions & SKU Specs'),
                            ]],
                        ]),
                        self::makeSection('Craftsmanship Tabs & Delivery FAQ', ['padding_y' => 32, 'bg_color' => '#f8fafc'], [
                            ['width' => 50, 'widgets' => [self::makeWidget('tabs', 'Materials & Dimensions Tabs')]],
                            ['width' => 50, 'widgets' => [self::makeWidget('accordion', 'White-Glove Delivery FAQ')]],
                        ]),
                    ],
                ],
            ],
            'category' => [
                'entity_kind'  => 'page_template',
                'section_type' => 'category',
                'title'        => 'Department Category Archive & Filter Layout',
                'hook_name'    => 'displayCategoryHeader',
                'layout'       => [
                    'version'  => '1.0',
                    'sections' => [
                        self::makeSection('Category Hero & Subcategory Pills', ['padding_y' => 28, 'bg_color' => '#f8fafc'], [
                            ['width' => 100, 'widgets' => [
                                self::makeWidget('breadcrumb', 'Breadcrumb Trail'),
                                self::makeWidget('category_description', 'Category Title & Intro'),
                                self::makeWidget('category_list', 'Subcategory Quick Pills'),
                            ]],
                        ]),
                        self::makeSection('Filtered Product Listing Grid', ['padding_y' => 32], [
                            ['width' => 25, 'widgets' => [self::makeWidget('faceted_filters', 'Sidebar Faceted Filters')]],
                            ['width' => 75, 'widgets' => [
                                self::makeWidget('active_filters', 'Active Filter Chips'),
                                self::makeWidget('product_grid', 'Category Product Grid'),
                                self::makeWidget('listing_pagination', 'Catalog Pagination'),
                            ]],
                        ]),
                    ],
                ],
            ],
            'footer' => [
                'entity_kind'  => 'page_template',
                'section_type' => 'footer',
                'title'        => 'Architectural Storefront Multi-Column Footer',
                'hook_name'    => 'displayFooter',
                'layout'       => [
                    'version'  => '1.0',
                    'sections' => [
                        self::makeSection('Footer Concierge & Links', ['padding_y' => 48, 'bg_color' => '#0f172a'], [
                            ['width' => 35, 'widgets' => [
                                self::makeWidget('shop_logo', 'Footer Brand Logo', [], ['text_color' => '#ffffff']),
                                self::makeWidget('text_editor', 'Brand Story', ['html' => '<p>Heirloom architectural furniture crafted for modern residential and commercial spaces.</p>'], ['text_color' => '#cbd5e1']),
                                self::makeWidget('social_icons', 'Social Channels', [], ['text_color' => '#ffffff']),
                            ]],
                            ['width' => 30, 'widgets' => [
                                self::makeWidget('icon_list', 'Showroom Services', [], ['text_color' => '#e2e8f0']),
                            ]],
                            ['width' => 35, 'widgets' => [
                                self::makeWidget('email_subscription', 'Footer Newsletter', [], ['text_color' => '#ffffff']),
                            ]],
                        ]),
                    ],
                ],
            ],
            'cms' => self::buildSectionBlueprint('cms', 'Editorial Story & Showroom Guide Page', 'displayCMSDisputeInformation', [
                self::makeWidget('page_title', 'Editorial Page Header'),
                self::makeWidget('text_editor', 'Craftsmanship Narrative'),
                self::makeWidget('google_maps', 'Showroom Location Map'),
                self::makeWidget('contact_form', 'Showroom Appointment Form'),
            ]),
            'account' => self::buildSectionBlueprint('account', 'Customer Concierge Account Portal', 'displayCustomerAccount', [
                self::makeWidget('heading', 'Welcome to Your Client Portal', ['text' => 'Manage Orders, Delivery Schedules & Moodboards']),
                self::makeWidget('account_nav_links', 'Customer Dashboard Cards'),
            ]),
            'prices_drop' => self::buildSectionBlueprint('prices_drop', 'Seasonal Archive Sale & Private Offers', 'displayWrapperTop', [
                self::makeWidget('countdown', 'Seasonal Event Countdown'),
                self::makeWidget('product_grid', 'Prices Drop Showcase Grid', ['source' => 'prices_drop', 'title' => 'Private Archive Event — Up to 35% Off']),
            ]),
            'new_products' => self::buildSectionBlueprint('new_products', 'New Season Lookbook Arrivals', 'displayWrapperTop', [
                self::makeWidget('animated_headline', 'New Collection Headline'),
                self::makeWidget('product_grid', 'New Arrivals Product Grid', ['source' => 'new_products', 'title' => 'Just Landed in the Showroom']),
            ]),
            'best_sales' => self::buildSectionBlueprint('best_sales', 'Most Coveted Bestsellers Showcase', 'displayWrapperTop', [
                self::makeWidget('star_rating', 'Verified Customer Rating'),
                self::makeWidget('product_grid', 'Bestsellers Product Grid', ['source' => 'best_sales', 'title' => 'Most-Loved Signature Pieces']),
            ]),
            'search' => self::buildSectionBlueprint('search', 'Instant Search Discovery & Trending Picks', 'displayWrapperTop', [
                self::makeWidget('ajax_search', 'Instant Catalog Search Bar'),
                self::makeWidget('category_list', 'Trending Search Departments'),
                self::makeWidget('product_grid', 'Recommended Search Results'),
            ]),
            'checkout' => self::buildSectionBlueprint('checkout', 'Reassurance & White-Glove Checkout Companion', 'displayShoppingCartFooter', [
                self::makeWidget('trust_badges', 'Checkout Protection & White-Glove Guarantee'),
                self::makeWidget('accordion', 'Delivery, Financing & Return Policy FAQ'),
            ]),
            'manufacturers' => self::buildSectionBlueprint('manufacturers', 'Artisan Makers & Design Studios Directory', 'displayWrapperTop', [
                self::makeWidget('heading', 'Our European & North American Atelier Partners'),
                self::makeWidget('articles_grid', 'Featured Design Houses & Makers'),
            ]),
            'page_404' => self::buildSectionBlueprint('page_404', 'Concierge 404 Recovery & Room Finder', 'displayWrapperTop', [
                self::makeWidget('call_to_action', '404 Concierge Recovery', [
                    'badge' => 'PAGE NOT FOUND (404)',
                    'title' => 'Let’s Guide You Back to the Showroom',
                    'text'  => 'The page or piece you were looking for may have moved. Search below or explore our top rooms.',
                    'cta_text' => 'Return to Flagship Homepage',
                    'cta_url'  => '/',
                ]),
                self::makeWidget('category_grid', 'Explore Rooms'),
            ]),
            'custom' => self::buildSectionBlueprint('custom', 'Custom Trade Program & Lookbook Landing Page', 'displayHome', [
                self::makeWidget('call_to_action', 'Interior Designer & Trade Partner Program'),
                self::makeWidget('flip_box', 'Complimentary Trade Swatch Library'),
                self::makeWidget('contact_form', 'Trade Account Application'),
            ]),
        ];
    }

    /**
     * @param array<int, array<string, mixed>> $widgets
     * @return array<string, mixed>
     */
    private static function buildSectionBlueprint(string $sectionType, string $title, string $hookName, array $widgets): array
    {
        return [
            'entity_kind'  => 'page_template',
            'section_type' => $sectionType,
            'title'        => $title,
            'hook_name'    => $hookName,
            'layout'       => self::buildSingleSectionLayout($title, [
                ['width' => 100, 'widgets' => $widgets],
            ]),
        ];
    }

    /**
     * @param array<int, array<string, mixed>> $columns
     * @return array<string, mixed>
     */
    private static function buildSingleSectionLayout(string $label, array $columns): array
    {
        return [
            'version'  => '1.0',
            'sections' => [
                self::makeSection($label, ['padding_y' => 36], $columns),
            ],
        ];
    }

    /**
     * @param array<string, mixed> $overrides
     * @param array<int, array<string, mixed>> $columns
     * @return array<string, mixed>
     */
    private static function makeSection(string $label, array $overrides, array $columns): array
    {
        static $secCounter = 1;
        $id = 'sec_bp_' . ($secCounter++);
        $py = (int) ($overrides['padding_y'] ?? 36);
        $px = (int) ($overrides['padding_x'] ?? 20);
        $gap = (int) ($overrides['gap'] ?? 24);

        $builtCols = [];
        foreach ($columns as $idx => $c) {
            $w = (float) ($c['width'] ?? 100);
            $builtCols[] = [
                'id'            => $id . '_col_' . ($idx + 1),
                'width'         => $w,
                'width_tablet'  => $w < 50 ? 50.0 : $w,
                'width_mobile'  => 100.0,
                'align_items'   => 'stretch',
                'padding'       => ['desktop' => 12, 'tablet' => 10, 'mobile' => 8],
                'bg_color'      => 'transparent',
                'border_radius' => 0,
                'widgets'       => $c['widgets'] ?? [],
            ];
        }

        return [
            'id'             => $id,
            'label'          => $label,
            'layout_mode'    => $overrides['layout_mode'] ?? 'boxed',
            'max_width'      => (int) ($overrides['max_width'] ?? 1280),
            'gap'            => ['desktop' => $gap, 'tablet' => max(16, $gap - 4), 'mobile' => 16],
            'padding_y'      => ['desktop' => $py, 'tablet' => max(20, (int) round($py * 0.75)), 'mobile' => max(16, (int) round($py * 0.6))],
            'padding_x'      => ['desktop' => $px, 'tablet' => 16, 'mobile' => 14],
            'bg_color'       => $overrides['bg_color'] ?? 'transparent',
            'bg_image'       => '',
            'reverse_mobile' => !empty($overrides['reverse_mobile']),
            'hide_desktop'   => false,
            'hide_tablet'    => false,
            'hide_mobile'    => false,
            'columns'        => $builtCols,
        ];
    }

    /**
     * @param array<string, mixed> $contentOverrides
     * @param array<string, mixed> $styleOverrides
     * @return array<string, mixed>
     */
    public static function makeWidget(string $type, string $label = '', array $contentOverrides = [], array $styleOverrides = []): array
    {
        static $wCounter = 1;
        $def = self::getWidget($type);
        $content = array_merge($def['defaults'] ?? [], $contentOverrides);
        $style = array_merge([
            'text_color'    => '#0f172a',
            'bg_color'      => 'transparent',
            'accent_color'  => '#2563eb',
            'border_color'  => '#e2e8f0',
            'font_size'     => ['desktop' => 16, 'tablet' => 15, 'mobile' => 15],
            'font_weight'   => 500,
            'line_height'   => 1.5,
            'text_align'    => ['desktop' => 'left', 'tablet' => 'left', 'mobile' => 'left'],
            'border_radius' => 10,
            'border_width'  => 0,
            'columns_count' => ['desktop' => 4, 'tablet' => 2, 'mobile' => 1],
            'shadow'        => 'none',
        ], $styleOverrides);

        return [
            'id'       => 'w_bp_' . ($wCounter++),
            'type'     => $type,
            'label'    => $label !== '' ? $label : ($def['label'] ?? $type),
            'content'  => $content,
            'style'    => $style,
            'advanced' => [
                'padding_y'    => ['desktop' => 12, 'tablet' => 10, 'mobile' => 8],
                'padding_x'    => ['desktop' => 14, 'tablet' => 12, 'mobile' => 10],
                'margin_y'     => ['desktop' => 0, 'tablet' => 0, 'mobile' => 0],
                'z_index'      => 1,
                'hide_desktop' => false,
                'hide_tablet'  => false,
                'hide_mobile'  => false,
                'custom_class' => '',
                'custom_css'   => '',
            ],
        ];
    }
}
