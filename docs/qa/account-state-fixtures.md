# Account State QA Fixtures

Seed command:

```bash
php artisan db:seed --class=QaAccountStateSeeder
```

Password for every QA account:

```text
RenyQA2026!
```

Accounts:

```text
State            Email
---------------  -----------------------------------------
Open             qa.open@renyrenteria.test
Royal active     qa.royal.active@renyrenteria.test
Royal expired    qa.royal.expired@renyrenteria.test
Royal refunded   qa.royal.refunded@renyrenteria.test
Payment failed   qa.royal.payment_failed@renyrenteria.test
```

Primary QA routes:

- `/account`
- `/royal/content/vip-mix`
- `/content/{content}` for CMS protected content
- `/api/public-content/videos`
- `/login`
- `/register`
- `/store`

Expected guard behavior:

- Guest users are redirected to `/login` with the original protected URL preserved by the auth intended URL flow.
- Open users land on an upgrade paywall.
- Expired users land on a reactivation paywall.
- Refunded users land on a refunded-pass paywall.
- Payment-failed users land on an update-payment paywall.
- Active Royal users can view protected content.
