# QA Account State Fixtures

Run the fixtures with:

```bash
RENY_QA_PASSWORD="set-a-local-secret" php artisan db:seed --class=QaAccountStateSeeder
```

In production-like environments the seeder exits unless `RENY_ALLOW_QA_FIXTURES=true` is set.

The seeder fails unless `RENY_QA_PASSWORD` is set. Use a fresh local/shared QA password for each environment.

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
