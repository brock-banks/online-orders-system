<?php
require_once 'config.php';

// Fetch the footer details from the database
$footerInfo = query("SELECT * FROM footer_info LIMIT 1")->fetch();

$map = $footerInfo['map'] ?? '<iframe src="https://maps.google.com/maps?q=Seeb,%20Muscat,%20Oman&t=&z=13&ie=UTF8&iwloc=&output=embed" width="100%" height="200" style="border:0;" allowfullscreen="" loading="lazy"></iframe>';
$address = $footerInfo['address'] ?? '300 Halban St, Seeb, Muscat, Oman';
$phone = $footerInfo['phone'] ?? '+249 11 909 9743';
$email = $footerInfo['email'] ?? 'brocksm123@gmail.com';
$createdBy = $footerInfo['created_by'] ?? '<a href="https://github.com/brock-banks" target="_blank">Brock</a>';
?>
<footer class="bg-light text-lg-start mt-5">
    <div class="container py-4">
        <div class="row">
            <div class="col-md-6 footer-contact">
                <h5>Contact Us</h5>
                <p><strong>Address:</strong> <?php echo htmlspecialchars($address); ?></p>
                <p><strong>Phone:</strong> <?php echo htmlspecialchars($phone); ?></p>
                <p><strong>Email:</strong> <a href="mailto:<?php echo htmlspecialchars($email); ?>"><?php echo htmlspecialchars($email); ?></a></p>
            </div>
            <div class="col-md-6">
                <?php echo $map; ?>
            </div>
        </div>
        <div class="footer-created-by py-2 text-center">
            Created by <?php echo $createdBy; ?>
        </div>
    </div>
</footer>