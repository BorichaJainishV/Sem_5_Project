<?php
session_start();
include 'header.php';
?>

<style>
    .size-guide-switcher {
        display: inline-flex;
        gap: 0.75rem;
        padding: 0.75rem;
        background: linear-gradient(135deg, rgba(99, 102, 241, 0.08), rgba(129, 140, 248, 0.06));
        border-radius: 999px;
        margin-bottom: 2.5rem;
    }
    .size-toggle {
        padding: 0.55rem 1.35rem;
        border-radius: 999px;
        border: 1px solid rgba(99, 102, 241, 0.2);
        background: rgba(255, 255, 255, 0.92);
        color: #3730a3;
        font-weight: 600;
        font-size: 0.9rem;
        transition: all 0.2s ease-in-out;
    }
    .size-toggle:hover {
        border-color: rgba(99, 102, 241, 0.35);
        box-shadow: 0 10px 25px rgba(79, 70, 229, 0.12);
    }
    .size-toggle.is-active {
        background: linear-gradient(140deg, rgba(99, 102, 241, 0.95), rgba(79, 70, 229, 0.9));
        color: #f8fafc;
        border-color: rgba(79, 70, 229, 0.4);
        box-shadow: 0 15px 35px rgba(79, 70, 229, 0.25);
    }
    .size-panel {
        display: none;
        animation: fadeIn 0.25s ease-in;
    }
    .size-panel.is-active {
        display: block;
    }
    .size-note {
        margin-top: 1rem;
        padding: 0.85rem 1rem;
        border-radius: 0.85rem;
        background: rgba(99, 102, 241, 0.09);
        color: #3730a3;
        font-size: 0.85rem;
    }
    @keyframes fadeIn {
        from { opacity: 0; transform: translateY(6px); }
        to { opacity: 1; transform: translateY(0); }
    }
    @media (max-width: 640px) {
        .size-guide-switcher {
            flex-direction: column;
            border-radius: 1rem;
        }
        .size-toggle {
            width: 100%;
            text-align: center;
        }
    }
</style>

<div class="page-header">
    <div class="container">
        <h1>Size Guide</h1>
        <p>Find your perfect fit for our mystical garments.</p>
    </div>
</div>

<div class="container">
    <div class="size-guide-switcher" role="tablist" aria-label="Apparel size charts">
        <button type="button" class="size-toggle is-active" data-target="catalog-sizing" id="catalog-toggle" aria-selected="true">Catalog Apparel</button>
        <button type="button" class="size-toggle" data-target="custom-sizing" id="custom-toggle" aria-selected="false">Custom Apparel</button>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-12 items-start">
        <div class="space-y-12">
            <section id="catalog-sizing" class="size-panel is-active" aria-hidden="false" aria-labelledby="catalog-toggle">
                <h2 class="mb-4">Catalog Apparel Measurements</h2>
                <p class="text-muted mb-6">These sizes match the ready-to-ship catalog tees and hoodies. All measurements are in inches; when between sizes, pick the larger option for breathing room.</p>
                <table class="content-table">
                    <thead>
                        <tr>
                            <th>Size</th>
                            <th>Chest (in)</th>
                            <th>Waist (in)</th>
                            <th>Hips (in)</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td><strong>XS</strong></td>
                            <td>32-34</td>
                            <td>26-28</td>
                            <td>32-34</td>
                        </tr>
                        <tr>
                            <td><strong>S</strong></td>
                            <td>34-36</td>
                            <td>28-30</td>
                            <td>34-36</td>
                        </tr>
                        <tr>
                            <td><strong>M</strong></td>
                            <td>38-40</td>
                            <td>32-34</td>
                            <td>38-40</td>
                        </tr>
                        <tr>
                            <td><strong>L</strong></td>
                            <td>42-44</td>
                            <td>36-38</td>
                            <td>42-44</td>
                        </tr>
                        <tr>
                            <td><strong>XL</strong></td>
                            <td>46-48</td>
                            <td>40-42</td>
                            <td>46-48</td>
                        </tr>
                        <tr>
                            <td><strong>XXL</strong></td>
                            <td>50-52</td>
                            <td>44-46</td>
                            <td>50-52</td>
                        </tr>
                    </tbody>
                </table>
                <p class="size-note">Catalog garments follow a classic unisex fit with a tolerance of ±0.5&quot; per measurement.</p>
            </section>

            <section id="custom-sizing" class="size-panel" aria-hidden="true" aria-labelledby="custom-toggle">
                <h2 class="mb-4">Custom Product Fit Map</h2>
                <p class="text-muted mb-6">Use these measurements when configuring made-to-order runs. We add a 2&quot; ease allowance so the final garment matches the requested body fit.</p>
                <table class="content-table">
                    <thead>
                        <tr>
                            <th>Size</th>
                            <th>Body Chest Fit (in)</th>
                            <th>Garment Chest (in)</th>
                            <th>Garment Length (in)</th>
                            <th>Sleeve Length (in)</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td><strong>XS</strong></td>
                            <td>34</td>
                            <td>36</td>
                            <td>26</td>
                            <td>7.5</td>
                        </tr>
                        <tr>
                            <td><strong>S</strong></td>
                            <td>36</td>
                            <td>38</td>
                            <td>27</td>
                            <td>8.0</td>
                        </tr>
                        <tr>
                            <td><strong>M</strong></td>
                            <td>38</td>
                            <td>40</td>
                            <td>28</td>
                            <td>8.25</td>
                        </tr>
                        <tr>
                            <td><strong>L</strong></td>
                            <td>41</td>
                            <td>43</td>
                            <td>29</td>
                            <td>8.5</td>
                        </tr>
                        <tr>
                            <td><strong>XL</strong></td>
                            <td>44</td>
                            <td>46</td>
                            <td>30</td>
                            <td>8.75</td>
                        </tr>
                        <tr>
                            <td><strong>XXL</strong></td>
                            <td>47</td>
                            <td>49</td>
                            <td>31</td>
                            <td>9.0</td>
                        </tr>
                    </tbody>
                </table>
                <p class="size-note">Need a bespoke spec? Share your target numbers via the <a href="support_artwork.php" class="text-primary font-semibold">Artwork Support team</a> and our pattern desk will confirm feasibility.</p>
            </section>
        </div>

        <div class="card p-8">
            <h3 class="text-2xl mb-6">How to Measure</h3>
            <ul class="space-y-6">
                <li class="flex items-start gap-4">
                    <i data-feather="chevrons-right" class="w-6 h-6 text-primary mt-1"></i>
                    <div>
                        <h4 class="font-semibold text-dark">Chest</h4>
                        <p class="text-muted">Measure under your arms, around the fullest part of your chest.</p>
                    </div>
                </li>
                <li class="flex items-start gap-4">
                    <i data-feather="chevrons-right" class="w-6 h-6 text-primary mt-1"></i>
                    <div>
                        <h4 class="font-semibold text-dark">Waist</h4>
                        <p class="text-muted">Measure around your natural waistline, keeping the tape a bit loose.</p>
                    </div>
                </li>
                <li class="flex items-start gap-4">
                    <i data-feather="chevrons-right" class="w-6 h-6 text-primary mt-1"></i>
                    <div>
                        <h4 class="font-semibold text-dark">Hips</h4>
                        <p class="text-muted">Measure around the fullest part of your body at the top of your legs.</p>
                    </div>
                </li>
            </ul>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        const toggles = document.querySelectorAll('.size-toggle');
        const panels = document.querySelectorAll('.size-panel');

        const activatePanel = (targetId) => {
            panels.forEach((panel) => {
                const isActive = panel.id === targetId;
                panel.classList.toggle('is-active', isActive);
                panel.setAttribute('aria-hidden', isActive ? 'false' : 'true');
            });

            toggles.forEach((toggle) => {
                const isActive = toggle.dataset.target === targetId;
                toggle.classList.toggle('is-active', isActive);
                toggle.setAttribute('aria-selected', isActive ? 'true' : 'false');
                toggle.setAttribute('aria-pressed', isActive ? 'true' : 'false');
            });
        };

        toggles.forEach((toggle) => {
            toggle.addEventListener('click', () => {
                const targetId = toggle.dataset.target;
                activatePanel(targetId);
                history.replaceState(null, '', '#' + targetId);
            });
        });

        const hash = window.location.hash.replace('#', '');
        if (hash && document.getElementById(hash)) {
            activatePanel(hash);
        }
    });
</script>

<?php
include 'footer.php';
?>