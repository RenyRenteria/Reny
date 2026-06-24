# QA Account State Fixtures

Run the fixtures with:

```bash
RENY_QA_PASSWORD="use-a-private-password" \
php artisan db:seed --class=QaAccountStateSeeder
```

The seeder requires `RENY_QA_PASSWORD` and fails if it is missing or blank.
In production-like environments it exits unless `RENY_ALLOW_QA_FIXTURES=true` is also set.

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
