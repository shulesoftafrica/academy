# Skills catalogue and assessments seed (2026-11-25)

Adds 42 skills (and two categories, Human Resources and Operations & Support Services) and gives every skill that has no
assessment a 12-question Professional-level assessment: 51 assessments, 612 questions. Skills that already have an assessment
(Sales) are left alone.

- `skills_catalog_seed.sql` is the file to run. It is one transaction and safe to run twice: existing skills, competencies and
  assessments are skipped. Dry run first: replace the final `COMMIT;` with `ROLLBACK;`.
- Level model: every assessment is Professional (skill_levels id 4, rank 3). A pass (70%, no critical competency under 60%)
  awards Professional; 50-69% awards Practitioner. Price is TZS 10,000, the same as the existing Sales assessment.
- Questions are AI-written (ai_generated = 1) and were checked by `validate_assess.php`, then length-balanced so the right
  answer is the longest option about 1 time in 4. A subject expert should still review them, especially the Kiswahili and
  safety (first aid, child safeguarding, driving) assessments.
- Sources are the `assess_*.json` files and `skills_spec.json`; regenerate the SQL with `php build_seed_sql.php`.
