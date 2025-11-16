<?php
include 'header.php';
include 'db_connection.php';
require_once __DIR__ . '/core/social_proof.php';

if (!function_exists('resolve_image_dimensions')) {
    function resolve_image_dimensions(string $relativePath): array
    {
        static $cache = [];
        if ($relativePath === '' || isset($cache[$relativePath])) {
            return $cache[$relativePath] ?? [null, null];
        }

        $cache[$relativePath] = [null, null];
        $parsed = parse_url($relativePath);
        if (!empty($parsed['scheme'])) {
            return $cache[$relativePath];
        }

        $normalizedPath = ltrim($relativePath, '/\\');
        $absolute = __DIR__ . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $normalizedPath);

        if (is_file($absolute)) {
            $info = @getimagesize($absolute);
            if (is_array($info) && count($info) >= 2) {
                $cache[$relativePath] = [(int) $info[0], (int) $info[1]];
            }
        }

        return $cache[$relativePath];
    }
}

$spotlightDesigns = [];
$spotlightRoot = __DIR__ . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'designs';

if (is_dir($spotlightRoot)) {
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($spotlightRoot, FilesystemIterator::SKIP_DOTS)
    );

    $designGroups = [];

    foreach ($iterator as $fileInfo) {
        if (!$fileInfo->isFile()) {
            continue;
        }

        $extension = strtolower($fileInfo->getExtension());
        if (!in_array($extension, ['png', 'jpg', 'jpeg', 'webp', 'gif'], true)) {
            continue;
        }

        $relativePath = substr($fileInfo->getPathname(), strlen(__DIR__) + 1);
        $relativePath = str_replace('\\', '/', $relativePath);

        $folderName = basename($fileInfo->getPath());

        $filename = $fileInfo->getFilename();
        $nameWithoutExt = pathinfo($filename, PATHINFO_FILENAME);
        $nameLower = strtolower($nameWithoutExt);

        $variant = 'front';
        $baseName = $nameWithoutExt;

        if (preg_match('/^(.*)_(front|back|texture|map)$/i', $nameWithoutExt, $matches)) {
            $baseName = $matches[1];
            $variant = strtolower($matches[2]);
        } elseif (in_array($nameLower, ['front', 'back', 'texture', 'map'], true)) {
            $variant = $nameLower;
            $baseName = basename($fileInfo->getPath());
        }

        if ($variant === 'texture') {
            $variant = 'map';
        }

        if (!in_array($variant, ['front', 'back', 'map'], true)) {
            $variant = 'front';
        }

        $relativeDir = trim(str_replace('\\', '/', dirname($relativePath)), '/');
        $groupKeyParts = [];
        if ($relativeDir !== '') {
            $groupKeyParts[] = $relativeDir;
        }
        $groupKeyParts[] = $baseName;
        $groupKey = implode('/', $groupKeyParts);

        $labelSource = $baseName ?: $folderName;
        $labelSource = trim($labelSource);
        if ($labelSource === '' || strtolower($labelSource) === 'front' || strtolower($labelSource) === 'back') {
            $labelSource = $folderName;
        }
        $label = preg_replace('/[_-]+/', ' ', (string) $labelSource);
        $label = trim($label) !== '' ? ucwords(trim($label)) : 'Spotlight Design';

        if (!isset($designGroups[$groupKey])) {
            $designGroups[$groupKey] = [
                'label' => $label,
                'folder' => $folderName,
                'timestamp' => $fileInfo->getMTime(),
                'views' => [],
                'dimensions' => [],
            ];
        } else {
            $designGroups[$groupKey]['timestamp'] = max($designGroups[$groupKey]['timestamp'], $fileInfo->getMTime());
        }

        $designGroups[$groupKey]['views'][$variant] = $relativePath;

        [$width, $height] = resolve_image_dimensions($relativePath);
        if (!empty($width) && !empty($height)) {
            $designGroups[$groupKey]['dimensions'][$variant] = [(int) $width, (int) $height];
        }
    }

    if (!empty($designGroups)) {
        foreach ($designGroups as $group) {
            if (empty($group['views'])) {
                continue;
            }

            $primaryVariant = 'map';
            if (!isset($group['views'][$primaryVariant])) {
                if (isset($group['views']['front'])) {
                    $primaryVariant = 'front';
                } elseif (isset($group['views']['back'])) {
                    $primaryVariant = 'back';
                } else {
                    $fallbackKeys = array_keys($group['views']);
                    $primaryVariant = $fallbackKeys ? reset($fallbackKeys) : 'map';
                }
            }

            $primaryPath = $group['views'][$primaryVariant];
            $primaryDimensions = $group['dimensions'][$primaryVariant] ?? [null, null];

            $spotlightDesigns[] = [
                'path' => $primaryPath,
                'label' => $group['label'],
                'timestamp' => $group['timestamp'],
                'folder' => $group['folder'],
                'width' => $primaryDimensions[0] ?? null,
                'height' => $primaryDimensions[1] ?? null,
                'views' => $group['views'],
                'dimensions' => $group['dimensions'],
            ];
        }

        usort($spotlightDesigns, static function ($a, $b) {
            return $b['timestamp'] <=> $a['timestamp'];
        });

        $spotlightDesigns = array_slice($spotlightDesigns, 0, 10);
    }
}

// Pull recent compliments for social proof surfaces before rendering
$socialProofEntries = get_recent_compliments_for_social_proof($conn, 8);

$featured = $conn->query("SELECT * FROM inventory WHERE stock_qty > 0 AND (is_archived = 0 OR is_archived IS NULL) ORDER BY inventory_id DESC LIMIT 4");
?>

<main>
    <section class="hero">
        <div class="container">
            <h1>Wear Your Imagination</h1>
            <p>The ultimate platform to design, print, and purchase high-quality custom apparel. Your style, your rules.</p>
            <a href="design3d.php" class="btn btn-primary btn-lg">Start Designing Now</a>
        </div>
    </section>

    <?php if (!empty($socialProofEntries)): ?>
    <section class="social-proof-strip">
        <div class="container">
            <div class="social-proof-heading">
                <span class="social-proof-heading__title">Recent compliments</span>
                <span class="social-proof-heading__meta">Powered by 4★+ customer feedback</span>
            </div>
            <div class="social-proof-scroll">
                <?php foreach ($socialProofEntries as $entry):
                    $quote = $entry['quote'];
                    if (strlen($quote) > 140) {
                        $quote = substr($quote, 0, 137) . '...';
                    }
                ?>
                <figure class="social-proof-pill">
                    <figcaption>
                        <span class="social-proof-pill__meta">★ <?php echo (int)$entry['rating']; ?>/5 · <?php echo htmlspecialchars($entry['product']); ?> · <?php echo htmlspecialchars($entry['date']); ?></span>
                        <p class="social-proof-pill__quote">“<?php echo htmlspecialchars($quote); ?>”</p>
                        <span class="social-proof-pill__customer">— <?php echo htmlspecialchars($entry['customer']); ?></span>
                    </figcaption>
                </figure>
                <?php endforeach; ?>
            </div>
        </div>
    </section>
    <?php endif; ?>

    <?php if (!empty($spotlightDesigns)): ?>
    <section class="container spotlight-section">
        <div class="text-center mb-12">
            <h2>Design Spotlight</h2>
            <p class="text-muted">Fresh community creations we loved enough to showcase.</p>
        </div>
        <div class="spotlight-grid">
            <?php foreach ($spotlightDesigns as $design): ?>
            <?php
                $shareCount = (abs(crc32($design['path'])) % 450) + 60;
                $saveCount = (abs(crc32($design['folder'])) % 180) + 20;
                $variantLabels = [
                    'front' => 'Front View',
                    'back' => 'Back View',
                    'map' => 'Design Map'
                ];
                $variantData = [];
                foreach ($variantLabels as $variantKey => $variantLabel) {
                    if (empty($design['views'][$variantKey])) {
                        continue;
                    }
                    $variantPath = $design['views'][$variantKey];
                    $variantDimensions = $design['dimensions'][$variantKey] ?? [null, null];
                    $variantData[$variantKey] = [
                        'label' => $variantLabel,
                        'path' => $variantPath,
                        'dimensions' => $variantDimensions,
                    ];
                }

                $primaryVariant = isset($variantData['map']) ? 'map' : (isset($variantData['front']) ? 'front' : (isset($variantData['back']) ? 'back' : array_key_first($variantData)));
                $primaryVariant = $primaryVariant ?: 'map';
                $primaryPath = $variantData[$primaryVariant]['path'] ?? $design['path'];
                $primaryDimensions = $variantData[$primaryVariant]['dimensions'] ?? [$design['width'], $design['height']];
                $primaryAlt = trim($design['label'] . ' ' . ($variantLabels[$primaryVariant] ?? '')) ?: $design['label'];

                $mapPath = $variantData['map']['path'] ?? $design['path'];
                $textureParam = rawurlencode($mapPath);
                $inspirationLink = 'design3d.php?inspiration_texture=' . $textureParam;

                $remixBaseParams = [
                    'remix_source' => $design['folder'],
                    'remix_origin' => $design['label'],
                    'remix_token' => substr(hash('sha256', $design['path'] . '|' . $design['timestamp']), 0, 12),
                ];
                $remixBaseQuery = http_build_query($remixBaseParams, '', '&', PHP_QUERY_RFC3986);

                $initialVariantLabel = $variantData[$primaryVariant]['label'] ?? ($variantLabels[$primaryVariant] ?? ucfirst($primaryVariant));
                $remixParams = $remixBaseParams;
                $remixParams['remix_variant'] = $primaryVariant;
                if (!empty($primaryPath)) {
                    $remixParams['remix_texture'] = $primaryPath;
                }
                if (!empty($initialVariantLabel)) {
                    $remixParams['remix_label'] = $initialVariantLabel;
                }

                $remixHref = 'design3d.php?' . http_build_query($remixParams, '', '&', PHP_QUERY_RFC3986);
                $remixBaseUrl = 'design3d.php?' . $remixBaseQuery;
            ?>
            <div class="product-card spotlight-card" data-parallax>
                <div class="spotlight-media" data-active-variant="<?php echo htmlspecialchars($primaryVariant); ?>">
                    <img
                        class="spotlight-media-img"
                        src="<?php echo htmlspecialchars($primaryPath); ?>"
                        alt="<?php echo htmlspecialchars($primaryAlt); ?>"
                        loading="lazy"
                        decoding="async"<?php if (!empty($primaryDimensions[0]) && !empty($primaryDimensions[1])): ?> width="<?php echo (int) $primaryDimensions[0]; ?>" height="<?php echo (int) $primaryDimensions[1]; ?>"<?php endif; ?>>
                    <?php if (count($variantData) > 1): ?>
                    <div class="spotlight-variant-toggle" role="group" aria-label="Toggle design view">
                        <?php foreach ($variantData as $variantKey => $variantMeta):
                            $isActive = $variantKey === $primaryVariant;
                            $dimensions = $variantMeta['dimensions'];
                            $variantAlt = trim($design['label'] . ' ' . $variantMeta['label']) ?: $design['label'];
                        ?>
                        <button
                            type="button"
                            class="spotlight-variant-button<?php echo $isActive ? ' active' : ''; ?>"
                            data-variant="<?php echo htmlspecialchars($variantKey); ?>"
                            data-src="<?php echo htmlspecialchars($variantMeta['path']); ?>"
                            data-alt="<?php echo htmlspecialchars($variantAlt); ?>"
                            data-label="<?php echo htmlspecialchars($variantMeta['label']); ?>"
                            aria-pressed="<?php echo $isActive ? 'true' : 'false'; ?>"
                            <?php if (!empty($dimensions[0]) && !empty($dimensions[1])): ?>data-width="<?php echo (int) $dimensions[0]; ?>" data-height="<?php echo (int) $dimensions[1]; ?>"<?php endif; ?>>
                            <?php echo htmlspecialchars($variantMeta['label']); ?>
                        </button>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                </div>
                <div class="product-card-content">
                    <h3><?php echo htmlspecialchars($design['label']); ?></h3>
                    <div class="spotlight-meta">
                        <span class="spotlight-date"><?php echo htmlspecialchars(date('M j, Y', $design['timestamp'])); ?></span>
                        <span class="spotlight-social">
                            <span><i data-feather="heart"></i><?php echo $shareCount; ?></span>
                            <span><i data-feather="bookmark"></i><?php echo $saveCount; ?></span>
                        </span>
                    </div>
                    <div class="spotlight-actions">
                        <a class="btn btn-ghost spotlight-view-link" href="<?php echo htmlspecialchars($primaryPath); ?>" target="_blank" rel="noopener">View Full</a>
                        <a
                            class="btn btn-secondary spotlight-remix-link"
                            href="<?php echo htmlspecialchars($remixHref); ?>"
                            data-remix-base-url="<?php echo htmlspecialchars($remixBaseUrl); ?>"
                        >Remix This</a>
                        <a class="btn btn-primary" href="<?php echo htmlspecialchars($inspirationLink); ?>">Use This Vibe</a>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </section>
    <?php endif; ?>

    <?php if (!empty($spotlightDesigns)):
        $covenTiles = array_slice($spotlightDesigns, 0, 6);
    ?>
    <section class="container coven-gallery">
        <div class="gallery-header">
            <div>
                <h2>Latest from the Coven</h2>
                <p class="text-muted">Tap into the community feed and remix what other makers are crafting.</p>
            </div>
            <a href="design3d.php" class="btn btn-primary">Share your design</a>
        </div>
        <div class="coven-masonry">
            <?php foreach ($covenTiles as $tile):
                $tileCaption = $tile['label'] . ' • ' . date('M j', $tile['timestamp']);
            ?>
            <figure class="coven-card" data-parallax>
                <img src="<?php echo htmlspecialchars($tile['path']); ?>" alt="<?php echo htmlspecialchars($tile['label']); ?> inspiration" loading="lazy" decoding="async"<?php if (!empty($tile['width']) && !empty($tile['height'])): ?> width="<?php echo (int) $tile['width']; ?>" height="<?php echo (int) $tile['height']; ?>"<?php endif; ?>>
                <figcaption>
                    <span><?php echo htmlspecialchars($tileCaption); ?></span>
                    <button type="button" class="coven-save" data-save-trigger>
                        <i data-feather="bookmark"></i>
                        Save inspiration
                    </button>
                </figcaption>
            </figure>
            <?php endforeach; ?>
        </div>
    </section>
    <?php endif; ?>

    <section class="container">
        <div class="text-center mb-12">
            <h2>Featured Products</h2>
            <p class="text-muted">Discover our latest and most popular designs</p>
        </div>
        
        <div class="product-grid">
            <?php if ($featured && $featured->num_rows > 0): while($p = $featured->fetch_assoc()): ?>
                <div class="product-card" data-aos="fade-up">
                    <?php [$productWidth, $productHeight] = resolve_image_dimensions($p['image_url']); ?>
                    <img src="<?php echo htmlspecialchars($p['image_url']); ?>" alt="<?php echo htmlspecialchars($p['product_name']); ?>" loading="lazy" decoding="async"<?php if (!empty($productWidth) && !empty($productHeight)): ?> width="<?php echo (int) $productWidth; ?>" height="<?php echo (int) $productHeight; ?>"<?php endif; ?>>
                    <div class="product-card-content">
                        <h3><?php echo htmlspecialchars($p['product_name']); ?></h3>
                        <div class="product-card-price">₹<?php echo htmlspecialchars($p['price']); ?></div>
                        
                        <?php if ($p['inventory_id'] == 4): ?>
                            <a href="design3d.php" class="btn btn-primary w-full mt-4">Start Designing</a>
                        <?php else: ?>
                            <a href="cart_handler.php?action=add&id=<?php echo $p['inventory_id']; ?>" class="btn btn-primary w-full mt-4">Add to Cart</a>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endwhile; else: ?>
                <div class="col-span-4 text-center">
                    <p class="text-muted">No featured products available at the moment.</p>
                    <a href="shop.php" class="btn btn-outline mt-4">Browse All Products</a>
                </div>
            <?php endif; ?>
        </div>
    </section>

    <section class="container testimonials">
        <div class="text-center mb-12">
            <h2>What Our Coven Says</h2>
        </div>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
            <div class="card text-center p-8">
                <p class="text-lg italic text-body mb-4">"The quality of the fabric is magical! I ordered a custom design and it came out better than I ever imagined."</p>
                <p class="font-bold text-dark">- Luna Ravenwood</p>
            </div>
            <div class="card text-center p-8">
                <p class="text-lg italic text-body mb-4">"My 3D designed shirt gets so many compliments. The printing is sharp and has survived many washes."</p>
                <p class="font-bold text-dark">- Jax Starlight</p>
            </div>
            <div class="card text-center p-8">
                <p class="text-lg italic text-body mb-4">"Fast shipping and the designs are so unique. This is my go-to shop for all things mystic and cool."</p>
                <p class="font-bold text-dark">- Seraphina Moon</p>
            </div>
        </div>
    </section>
</main>

<script>
    // Show toast notification for login success
    document.addEventListener('DOMContentLoaded', function() {
        const urlParams = new URLSearchParams(window.location.search);
        if (urlParams.get('login') === 'success') {
            if (typeof toastSuccess === 'function') {
                toastSuccess('Welcome back! You\'re now logged in.');
            }
            // Clean up URL
            window.history.replaceState({}, document.title, window.location.pathname);
        }

        document.querySelectorAll('.spotlight-media').forEach((media) => {
            const img = media.querySelector('.spotlight-media-img');
            const toggle = media.querySelector('.spotlight-variant-toggle');
            if (!img || !toggle) {
                return;
            }

            toggle.addEventListener('click', (event) => {
                const button = event.target.closest('.spotlight-variant-button');
                if (!button || button.classList.contains('active')) {
                    return;
                }

                const newSrc = button.getAttribute('data-src');
                const newAlt = button.getAttribute('data-alt');
                const newWidth = button.getAttribute('data-width');
                const newHeight = button.getAttribute('data-height');
                const variant = button.getAttribute('data-variant') || '';

                if (newSrc) {
                    img.src = newSrc;
                }
                if (newAlt) {
                    img.alt = newAlt;
                }

                if (newWidth && newHeight) {
                    img.setAttribute('width', newWidth);
                    img.setAttribute('height', newHeight);
                } else {
                    img.removeAttribute('width');
                    img.removeAttribute('height');
                }

                toggle.querySelectorAll('.spotlight-variant-button').forEach((btn) => {
                    btn.classList.remove('active');
                    btn.setAttribute('aria-pressed', 'false');
                });
                button.classList.add('active');
                button.setAttribute('aria-pressed', 'true');

                media.setAttribute('data-active-variant', variant);

                const card = media.closest('.spotlight-card');
                if (card) {
                    const viewLink = card.querySelector('.spotlight-view-link');
                    if (viewLink && newSrc) {
                        viewLink.href = newSrc;
                    }

                    const remixLink = card.querySelector('.spotlight-remix-link');
                    if (remixLink) {
                        const baseUrl = remixLink.getAttribute('data-remix-base-url');
                        if (baseUrl) {
                            const params = [];
                            if (variant) {
                                params.push('remix_variant=' + encodeURIComponent(variant));
                            }
                            if (newSrc) {
                                params.push('remix_texture=' + encodeURIComponent(newSrc));
                            }
                            const variantLabel = button.getAttribute('data-label') || button.textContent.trim();
                            if (variantLabel) {
                                params.push('remix_label=' + encodeURIComponent(variantLabel));
                            }
                            const paramString = params.join('&');
                            remixLink.href = paramString
                                ? baseUrl + (baseUrl.includes('?') ? '&' : '?') + paramString
                                : baseUrl;
                        }
                    }
                }
            });
        });
    });
</script>

<?php include 'footer.php'; ?>
