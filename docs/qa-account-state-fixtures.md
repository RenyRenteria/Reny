# QA Account State Fixtures

Run the fixtures with:

```bash
php artisan db:seed --class=QaAccountStateSeeder
```

In production-like environments the seeder exits unless `RENY_ALLOW_QA_FIXTURES=true` is set.

Set `RENY_QA_PASSWORD` before running the seeder. The seeder fails when the password is missing so shared QA or staging environments do not get a known credential by accident.

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
