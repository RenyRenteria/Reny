# QA Account State Fixtures

Run the fixtures with:

```bash
php artisan db:seed --class=QaAccountStateSeeder
```

In production-like environments the seeder exits unless `RENY_ALLOW_QA_FIXTURES=true` is set.

All QA accounts use password `RenyQA!2026`.

```
State           Email
──────────────  ─────────────────────────────────────
Registered      qa+registered@renyrenteria.test
Royal active    qa+royal-active@renyrenteria.test
Royal expired   qa+royal-expired@renyrenteria.test
Payment failed  qa+payment-failed@renyrenteria.test
Refunded        qa+refunded@renyrenteria.test
```

Guest state is validated by signing out and visiting protected routes or public pages.
