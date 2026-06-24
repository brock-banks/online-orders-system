<?php
require_once 'config.php';

$footerInfo = query("SELECT * FROM footer_info LIMIT 1")->fetch();

$map = $footerInfo['map'] ?? '<iframe src="https://maps.google.com/maps?q=Seeb,%20Muscat,%20Oman&t=&z=13&ie=UTF8&iwloc=&output=embed" width="100%" height="200" style="border:0;" allowfullscreen="" loading="lazy"></iframe>';
$address = $footerInfo['address'] ?? '300 Halban St, Seeb, Muscat, Oman';
$phone = $footerInfo['phone'] ?? '+249 11 909 9743';
$email = $footerInfo['email'] ?? 'brocksm123@gmail.com';
$createdBy = $footerInfo['created_by'] ?? '<a href="https://github.com/brock-banks" target="_blank" rel="noopener">Brock</a>';

$brandName = $settings['header_name'] ?? 'Order System';
$year = date('Y');

$telHref = 'tel:' . preg_replace('/[^\d+]/', '', $phone);
?>
<style>
.site-footer {
    --footer-muted: #6b7280;
    --footer-border: rgba(0,0,0,.08);
    margin-top: 4rem;
    background: var(--card-bg, #fff);
    color: var(--text-color, #1f2937);
    border-top: 3px solid var(--primary, #568fc5);
}

.site-footer .footer-inner {
    padding: 3rem 0 1.25rem;
}

.site-footer .footer-grid {
    display: grid;
    grid-template-columns: 1.2fr 1fr 1.3fr;
    gap: 3rem;
    align-items: start;
    text-align: left;
}

.site-footer .footer-col {
    display: flex;
    flex-direction: column;
    align-items: flex-start;
    gap: .85rem;
    min-width: 0;
    text-align: left;
}

.site-footer .footer-eyebrow {
    font-size: .7rem;
    font-weight: 700;
    letter-spacing: .12em;
    text-transform: uppercase;
    color: var(--primary, #568fc5);
    margin: 0 0 .25rem;
    line-height: 1;
    text-align: left;
}

.site-footer .brand-name {
    font-weight: 700;
    font-size: 1.15rem;
    color: var(--text-color, #1f2937);
    margin: 0;
    line-height: 1.3;
    letter-spacing: -.01em;
    text-align: left;
}

.site-footer .brand-tagline {
    color: var(--footer-muted);
    font-size: .875rem;
    line-height: 1.55;
    margin: 0;
    max-width: 32ch;
    text-align: left;
}

.site-footer .contact-list {
    list-style: none;
    padding: 0;
    margin: 0;
    display: grid;
    gap: .65rem;
    width: 100%;
    text-align: left;
}

.site-footer .contact-list li {
    display: grid;
    grid-template-columns: 20px 1fr;
    gap: .6rem;
    align-items: start;
    justify-content: start;
    font-size: .875rem;
    line-height: 1.5;
    color: var(--text-color, #1f2937);
    text-align: left;
}

.site-footer .contact-list svg {
    width: 16px;
    height: 16px;
    margin-top: 3px;
    color: var(--primary, #568fc5);
    justify-self: start;
}

.site-footer .contact-list a {
    color: inherit;
    text-decoration: none;
    transition: color .15s ease;
}

.site-footer .contact-list a:hover {
    color: var(--primary, #568fc5);
    text-decoration: underline;
}

.site-footer a:focus-visible {
    outline: 2px solid var(--primary, #568fc5);
    outline-offset: 2px;
    border-radius: 2px;
}

.site-footer .map-wrap {
    border-radius: 10px;
    overflow: hidden;
    border: 1px solid var(--footer-border);
    box-shadow: 0 1px 3px rgba(0,0,0,.06);
    line-height: 0;
    width: 100%;
    max-width: 100%;
    align-self: stretch;
}

.site-footer .map-wrap iframe {
    display: block;
    width: 100% !important;
    height: 160px;
    border: 0;
    max-width: 100%;
}

.site-footer .footer-bottom {
    margin-top: 2.5rem;
    padding-top: 1.25rem;
    border-top: 1px solid var(--footer-border);
    display: grid;
    grid-template-columns: 1fr auto;
    gap: 1rem;
    align-items: center;
    font-size: .825rem;
    color: var(--footer-muted);
}

.site-footer .footer-bottom a {
    color: var(--primary, #568fc5);
    text-decoration: none;
    font-weight: 500;
}

.site-footer .footer-bottom a:hover { text-decoration: underline; }

@media (max-width: 991.98px) {
    .site-footer .footer-grid {
        grid-template-columns: 1fr 1fr;
        gap: 2rem;
    }
    .site-footer .footer-col:last-child {
        grid-column: 1 / -1;
    }
}

@media (max-width: 767.98px) {
    .site-footer { margin-top: 2.5rem; }
    .site-footer .footer-inner { padding: 2rem 0 1rem; }
    .site-footer .footer-grid {
        grid-template-columns: 1fr;
        gap: 1.75rem;
    }
    .site-footer .footer-col:last-child {
        grid-column: auto;
    }
    .site-footer .footer-bottom {
        grid-template-columns: 1fr;
        text-align: left;
        justify-items: start;
    }
}
</style>

<footer class="site-footer">
    <div class="container footer-inner">
        <div class="footer-grid">
            <div class="footer-col">
                <p class="footer-eyebrow">About</p>
                <p class="brand-name"><?php echo htmlspecialchars($brandName); ?></p>
                <p class="brand-tagline">Trusted online orders &amp; deliveries across Muscat.</p>
            </div>

            <div class="footer-col">
                <p class="footer-eyebrow">Contact</p>
                <ul class="contact-list">
                    <li>
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <path d="M20 10c0 7-8 12-8 12s-8-5-8-12a8 8 0 0 1 16 0z"/>
                            <circle cx="12" cy="10" r="3"/>
                        </svg>
                        <span><?php echo htmlspecialchars($address); ?></span>
                    </li>
                    <li>
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"/>
                        </svg>
                        <a href="<?php echo htmlspecialchars($telHref); ?>"><?php echo htmlspecialchars($phone); ?></a>
                    </li>
                    <li>
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/>
                            <polyline points="22,6 12,13 2,6"/>
                        </svg>
                        <a href="mailto:<?php echo htmlspecialchars($email); ?>"><?php echo htmlspecialchars($email); ?></a>
                    </li>
                </ul>
            </div>

            <div class="footer-col">
                <p class="footer-eyebrow">Find Us</p>
                <div class="map-wrap">
                    <?php echo $map; ?>
                </div>
            </div>
        </div>

        <div class="footer-bottom">
            <div>&copy; <?php echo $year; ?> <?php echo htmlspecialchars($brandName); ?>. All rights reserved.</div>
            <div>Created by <?php echo $createdBy; ?></div>
        </div>
    </div>
</footer>
