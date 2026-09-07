-- Sample data so the whole candidate workflow is testable end-to-end.
-- For every active skill: 3 competencies (if missing) + a PUBLISHED Foundation
-- assessment with 6 objective questions (auto-scored → instant result + credential),
-- and a price. Half are FREE (instant, no payment) and half PRICED (billing path).
-- Idempotent: skips a skill that already has a Foundation assessment.
SET search_path TO academy, public;

DO $$
DECLARE
  sk RECORD;
  vfound INT;
  aid INT;
  comps INT[];
  c1 INT; c2 INT; c3 INT;
  q INT;
  price_val NUMERIC;
BEGIN
  SELECT id INTO vfound FROM skill_levels WHERE slug = 'foundation';

  FOR sk IN SELECT id, slug, name FROM skills WHERE status = 1 ORDER BY id LOOP
    -- one Foundation assessment per skill
    IF EXISTS (SELECT 1 FROM assessments WHERE skill_id = sk.id AND skill_level_id = vfound) THEN
      CONTINUE;
    END IF;

    -- ensure at least 3 competencies
    SELECT array_agg(id ORDER BY id) INTO comps FROM skill_competencies WHERE skill_id = sk.id;
    IF comps IS NULL OR array_length(comps, 1) < 3 THEN
      INSERT INTO skill_competencies (skill_id, name, description, weight, is_critical, sort_order, status) VALUES
        (sk.id, sk.name || ' Fundamentals', 'Core concepts and terminology.', 40, 1, 1, 1),
        (sk.id, sk.name || ' Application',  'Applying knowledge to real tasks.', 35, 0, 2, 1),
        (sk.id, sk.name || ' Judgement',    'Making sound decisions on the job.', 25, 0, 3, 1);
      SELECT array_agg(id ORDER BY id) INTO comps FROM skill_competencies WHERE skill_id = sk.id;
    END IF;
    c1 := comps[1]; c2 := comps[2]; c3 := comps[3];

    price_val := CASE sk.slug
      WHEN 'accounting'           THEN 8000
      WHEN 'digital-marketing'    THEN 5000
      WHEN 'excel-data-analysis'  THEN 10000
      WHEN 'office-administration' THEN 5000
      WHEN 'software-development' THEN 15000
      ELSE 0 END;   -- others (communication, customer-service, leadership, sales, teaching) are FREE

    INSERT INTO assessments (skill_id, skill_level_id, title, description, duration_minutes, pass_score, critical_min, total_questions, max_attempts, cooldown_days, version, status)
    VALUES (sk.id, vfound, sk.name || ' — Foundation',
            'Prove your foundational ' || sk.name || ' knowledge and earn a verified credential.',
            15, 60, 50, 6, 3, 0, 1, 'published')
    RETURNING id INTO aid;

    -- Q1 single (critical competency c1)
    INSERT INTO assessment_questions (assessment_id, skill_id, competency_id, skill_level_id, question_type, question_text, difficulty, points, status)
    VALUES (aid, sk.id, c1, vfound, 'single', 'Which of the following best reflects good practice in ' || sk.name || '?', 'easy', 10, 'published') RETURNING id INTO q;
    INSERT INTO assessment_question_options (question_id, option_text, is_correct, sort_order) VALUES
      (q, 'Follow the established ' || sk.name || ' standard and verify the outcome.', 1, 1),
      (q, 'Skip the standard to save time.', 0, 2),
      (q, 'Rely on guesswork alone.', 0, 3),
      (q, 'Copy others without understanding why.', 0, 4);

    -- Q2 single (c2)
    INSERT INTO assessment_questions (assessment_id, skill_id, competency_id, skill_level_id, question_type, question_text, difficulty, points, status)
    VALUES (aid, sk.id, c2, vfound, 'single', 'When applying ' || sk.name || ' on the job, what should you do first?', 'easy', 10, 'published') RETURNING id INTO q;
    INSERT INTO assessment_question_options (question_id, option_text, is_correct, sort_order) VALUES
      (q, 'Understand the goal and the context before acting.', 1, 1),
      (q, 'Act immediately without checking requirements.', 0, 2),
      (q, 'Wait to be told exactly what to do every time.', 0, 3),
      (q, 'Assume last time''s approach always fits.', 0, 4);

    -- Q3 single (c3)
    INSERT INTO assessment_questions (assessment_id, skill_id, competency_id, skill_level_id, question_type, question_text, difficulty, points, status)
    VALUES (aid, sk.id, c3, vfound, 'single', 'A tricky ' || sk.name || ' situation needs judgement. What is the best approach?', 'medium', 10, 'published') RETURNING id INTO q;
    INSERT INTO assessment_question_options (question_id, option_text, is_correct, sort_order) VALUES
      (q, 'Weigh the options against the goal, then decide and review.', 1, 1),
      (q, 'Pick the first option that comes to mind.', 0, 2),
      (q, 'Avoid deciding and hope it resolves itself.', 0, 3),
      (q, 'Always choose the cheapest option regardless of impact.', 0, 4);

    -- Q4 true/false (c1)
    INSERT INTO assessment_questions (assessment_id, skill_id, competency_id, skill_level_id, question_type, question_text, difficulty, points, status)
    VALUES (aid, sk.id, c1, vfound, 'truefalse', 'In ' || sk.name || ', following the agreed standard improves consistency and quality.', 'easy', 10, 'published') RETURNING id INTO q;
    INSERT INTO assessment_question_options (question_id, option_text, is_correct, sort_order) VALUES
      (q, 'True', 1, 1), (q, 'False', 0, 2);

    -- Q5 multiple (c2) — two correct
    INSERT INTO assessment_questions (assessment_id, skill_id, competency_id, skill_level_id, question_type, question_text, difficulty, points, status)
    VALUES (aid, sk.id, c2, vfound, 'multiple', 'Which of the following are good ' || sk.name || ' practices? (select all that apply)', 'medium', 10, 'published') RETURNING id INTO q;
    INSERT INTO assessment_question_options (question_id, option_text, is_correct, sort_order) VALUES
      (q, 'Plan before you act.', 1, 1),
      (q, 'Check your work against the goal.', 1, 2),
      (q, 'Ignore feedback from others.', 0, 3),
      (q, 'Cut corners whenever possible.', 0, 4);

    -- Q6 single (c3)
    INSERT INTO assessment_questions (assessment_id, skill_id, competency_id, skill_level_id, question_type, question_text, difficulty, points, status)
    VALUES (aid, sk.id, c3, vfound, 'single', 'Which choice shows the strongest ' || sk.name || ' judgement?', 'medium', 10, 'published') RETURNING id INTO q;
    INSERT INTO assessment_question_options (question_id, option_text, is_correct, sort_order) VALUES
      (q, 'Balance quality, time and impact, and communicate the decision.', 1, 1),
      (q, 'Optimise one thing and ignore everything else.', 0, 2),
      (q, 'Do whatever avoids responsibility.', 0, 3),
      (q, 'Delay until someone else decides.', 0, 4);

    -- price (price 0 = free → checkout starts instantly)
    INSERT INTO assessment_products (skill_id, skill_level_id, assessment_id, price, currency, retake_price, active)
    VALUES (sk.id, vfound, aid, price_val, 'TZS', price_val, 1);

    RAISE NOTICE 'Seeded % — Foundation (%, price %)', sk.name, CASE WHEN price_val > 0 THEN 'PAID' ELSE 'FREE' END, price_val;
  END LOOP;
END $$;
