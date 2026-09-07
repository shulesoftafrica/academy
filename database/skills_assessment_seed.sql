-- =============================================================================
-- Skills Assessment seed — 5 levels, V1 skills+categories (§50), and a fully
-- worked "Sales — Professional" fixture (competencies §8/§9, standards §17,
-- assessment, price, sections, sample questions across types, rubric §30).
-- Re-runnable: every insert is guarded by a natural key.
-- =============================================================================
SET client_min_messages TO WARNING;
SET search_path TO academy;

-- ---- 5 standardized levels (§4) ---------------------------------------------
INSERT INTO skill_levels (name,slug,rank,description)
SELECT v.* FROM (VALUES
 ('Foundation','foundation',1,'Can perform basic tasks with guidance.'),
 ('Practitioner','practitioner',2,'Can independently perform common tasks.'),
 ('Professional','professional',3,'Independently handles complex tasks and makes sound decisions.'),
 ('Advanced','advanced',4,'Solves complex problems, optimizes processes and handles difficult situations.'),
 ('Expert / Lead','expert',5,'Designs strategy, solves novel problems, mentors others and leads the function.')
) v(name,slug,rank,description)
WHERE NOT EXISTS (SELECT 1 FROM skill_levels sl WHERE sl.slug=v.slug);

-- ---- categories (§2) --------------------------------------------------------
INSERT INTO skill_categories (name,slug,sort_order)
SELECT v.* FROM (VALUES
 ('Business','business',1),('Finance & Accounting','finance-accounting',2),
 ('Sales','sales',3),('Marketing','marketing',4),('Technology','technology',5),
 ('Administration','administration',6),('Education','education',7),
 ('Customer Service','customer-service',8),('Data & Analytics','data-analytics',9),
 ('Leadership','leadership',10)
) v(name,slug,sort_order)
WHERE NOT EXISTS (SELECT 1 FROM skill_categories c WHERE c.slug=v.slug);

-- ---- V1 skills (§50) --------------------------------------------------------
INSERT INTO skills (category_id,name,slug,description,validity_months)
SELECT (SELECT id FROM skill_categories WHERE slug=v.cat), v.name, v.slug, v.descr, v.validity
FROM (VALUES
 ('sales','Sales','sales','Prospecting, discovery, negotiation and closing.',24),
 ('finance-accounting','Accounting','accounting','Bookkeeping, financial statements and controls.',24),
 ('customer-service','Customer Service','customer-service','Handling customers professionally and effectively.',24),
 ('data-analytics','Excel / Data Analysis','excel-data-analysis','Spreadsheets, analysis and reporting.',NULL),
 ('marketing','Digital Marketing','digital-marketing','Campaigns, SEO and social media.',18),
 ('administration','Office Administration','office-administration','Coordination, records and office operations.',NULL),
 ('leadership','Leadership','leadership','Leading teams and driving performance.',24),
 ('technology','Software Development','software-development','Building and fixing software.',18),
 ('business','Communication','communication','Clear written and verbal communication.',NULL),
 ('education','Teaching / Classroom Practice','teaching','Lesson planning and classroom effectiveness.',24)
) v(cat,name,slug,descr,validity)
WHERE NOT EXISTS (SELECT 1 FROM skills s WHERE s.slug=v.slug);

-- ---- Sales competency framework (§8/§9) — weights sum to 100 ----------------
INSERT INTO skill_competencies (skill_id,name,description,weight,is_critical,sort_order)
SELECT (SELECT id FROM skills WHERE slug='sales'), v.name, v.descr, v.weight, v.crit, v.ord
FROM (VALUES
 ('Prospecting','Finding and qualifying leads',10,0,1),
 ('Customer Discovery','Understanding customer context',15,0,2),
 ('Needs Analysis','Identifying real customer needs',15,0,3),
 ('Presentation','Presenting the solution',10,0,4),
 ('Objection Handling','Addressing concerns credibly',15,1,5),
 ('Negotiation','Reaching mutually good agreement',15,1,6),
 ('Closing','Securing commitment',10,1,7),
 ('Pipeline Management','Managing the sales pipeline',10,0,8)
) v(name,descr,weight,crit,ord)
WHERE NOT EXISTS (SELECT 1 FROM skill_competencies c WHERE c.skill_id=(SELECT id FROM skills WHERE slug='sales') AND c.name=v.name);

-- ---- Professional standards per competency (§17) ----------------------------
INSERT INTO skill_competency_levels (competency_id,skill_level_id,minimum_score,description)
SELECT c.id, (SELECT id FROM skill_levels WHERE slug='professional'),
       CASE WHEN c.is_critical=1 THEN 60 ELSE 50 END, 'Professional minimum standard'
FROM skill_competencies c
WHERE c.skill_id=(SELECT id FROM skills WHERE slug='sales')
  AND NOT EXISTS (SELECT 1 FROM skill_competency_levels x
                  WHERE x.competency_id=c.id AND x.skill_level_id=(SELECT id FROM skill_levels WHERE slug='professional'));

-- ---- assessment + product + sections ---------------------------------------
INSERT INTO assessments (skill_id,skill_level_id,title,description,duration_minutes,pass_score,critical_min,total_questions,version,status)
SELECT (SELECT id FROM skills WHERE slug='sales'), (SELECT id FROM skill_levels WHERE slug='professional'),
       'Sales — Professional','Professional-level Sales assessment across the full competency framework.',60,70,60,4,1,'published'
WHERE NOT EXISTS (SELECT 1 FROM assessments WHERE skill_id=(SELECT id FROM skills WHERE slug='sales') AND skill_level_id=(SELECT id FROM skill_levels WHERE slug='professional'));

INSERT INTO assessment_products (skill_id,skill_level_id,assessment_id,price,currency,active)
SELECT a.skill_id,a.skill_level_id,a.id,10000,'TZS',1 FROM assessments a
WHERE a.skill_id=(SELECT id FROM skills WHERE slug='sales') AND a.skill_level_id=(SELECT id FROM skill_levels WHERE slug='professional')
  AND NOT EXISTS (SELECT 1 FROM assessment_products p WHERE p.assessment_id=a.id);

INSERT INTO assessment_sections (assessment_id,name,type,sort_order)
SELECT a.id, v.name, v.type, v.ord FROM assessments a,
 (VALUES ('Knowledge','knowledge',1),('Situational Judgment','situational',2),('Practical','practical',3)) v(name,type,ord)
WHERE a.skill_id=(SELECT id FROM skills WHERE slug='sales') AND a.skill_level_id=(SELECT id FROM skill_levels WHERE slug='professional')
  AND NOT EXISTS (SELECT 1 FROM assessment_sections s WHERE s.assessment_id=a.id AND s.type=v.type);

-- ---- sample questions (across types) ----------------------------------------
DO $$
DECLARE v_assess int; v_sales int; v_prof int; v_q int;
        c_prospect int; c_needs int; c_obj int; c_neg int;
BEGIN
  SELECT id INTO v_sales FROM skills WHERE slug='sales';
  SELECT id INTO v_prof  FROM skill_levels WHERE slug='professional';
  SELECT id INTO v_assess FROM assessments WHERE skill_id=v_sales AND skill_level_id=v_prof;
  SELECT id INTO c_prospect FROM skill_competencies WHERE skill_id=v_sales AND name='Prospecting';
  SELECT id INTO c_needs    FROM skill_competencies WHERE skill_id=v_sales AND name='Needs Analysis';
  SELECT id INTO c_obj      FROM skill_competencies WHERE skill_id=v_sales AND name='Objection Handling';
  SELECT id INTO c_neg      FROM skill_competencies WHERE skill_id=v_sales AND name='Negotiation';

  -- Q1 single choice (Knowledge / Prospecting)
  IF NOT EXISTS (SELECT 1 FROM assessment_questions WHERE assessment_id=v_assess AND question_text LIKE 'The primary goal of prospecting%') THEN
    INSERT INTO assessment_questions (assessment_id,section_id,skill_id,competency_id,skill_level_id,question_type,question_text,difficulty,points,status)
    VALUES (v_assess,(SELECT id FROM assessment_sections WHERE assessment_id=v_assess AND type='knowledge'),v_sales,c_prospect,v_prof,'single',
            'The primary goal of prospecting is to:','easy',10,'published') RETURNING id INTO v_q;
    INSERT INTO assessment_question_options (question_id,option_text,is_correct,sort_order) VALUES
     (v_q,'Identify and qualify potential customers',1,1),
     (v_q,'Close the deal immediately',0,2),
     (v_q,'Discount the product',0,3),
     (v_q,'Skip needs analysis',0,4);
  END IF;

  -- Q2 multiple response (Knowledge / Needs Analysis)
  IF NOT EXISTS (SELECT 1 FROM assessment_questions WHERE assessment_id=v_assess AND question_text LIKE 'Which of the following are open-ended%') THEN
    INSERT INTO assessment_questions (assessment_id,section_id,skill_id,competency_id,skill_level_id,question_type,question_text,difficulty,points,status)
    VALUES (v_assess,(SELECT id FROM assessment_sections WHERE assessment_id=v_assess AND type='knowledge'),v_sales,c_needs,v_prof,'multiple',
            'Which of the following are open-ended discovery questions? (select all that apply)','medium',20,'published') RETURNING id INTO v_q;
    INSERT INTO assessment_question_options (question_id,option_text,is_correct,sort_order) VALUES
     (v_q,'What challenges are you facing with your current process?',1,1),
     (v_q,'Do you like our product? (yes/no)',0,2),
     (v_q,'How does this problem affect your team?',1,3),
     (v_q,'Is your budget over 1M? (yes/no)',0,4);
  END IF;

  -- Q3 situational judgment (single, weighted options) (§14 example)
  IF NOT EXISTS (SELECT 1 FROM assessment_questions WHERE assessment_id=v_assess AND question_text LIKE 'A customer tells you your competitor%') THEN
    INSERT INTO assessment_questions (assessment_id,section_id,skill_id,competency_id,skill_level_id,question_type,question_text,difficulty,points,status)
    VALUES (v_assess,(SELECT id FROM assessment_sections WHERE assessment_id=v_assess AND type='situational'),v_sales,c_obj,v_prof,'single',
            'A customer tells you your competitor is offering the same product for 20% less. What is the best response?','hard',30,'published') RETURNING id INTO v_q;
    INSERT INTO assessment_question_options (question_id,option_text,is_correct,score,sort_order) VALUES
     (v_q,'Explore the value they need and quantify the difference in outcomes before discussing price',1,30,1),
     (v_q,'Immediately match the 20% discount',0,10,2),
     (v_q,'Tell them the competitor product is bad',0,0,3),
     (v_q,'End the conversation',0,0,4);
  END IF;

  -- Q4 practical (long text + rubric) (Negotiation) (§7C/§30)
  IF NOT EXISTS (SELECT 1 FROM assessment_questions WHERE assessment_id=v_assess AND question_text LIKE 'Given this customer profile%') THEN
    INSERT INTO assessment_questions (assessment_id,section_id,skill_id,competency_id,skill_level_id,question_type,question_text,difficulty,points,marking_criteria,status)
    VALUES (v_assess,(SELECT id FROM assessment_sections WHERE assessment_id=v_assess AND type='practical'),v_sales,c_neg,v_prof,'long_text',
            'Given this customer profile (mid-size school, budget-sensitive, values reliability), prepare a sales approach: outline discovery, value framing, objection handling and your closing plan.','hard',40,'Graded by rubric: discovery, communication, needs identification, objection handling, negotiation, closing.','published') RETURNING id INTO v_q;
    INSERT INTO assessment_rubrics (assessment_id,question_id,competency_id,criterion,max_score,weight,sort_order) VALUES
     (v_assess,v_q,c_needs,'Customer discovery',20,20,1),
     (v_assess,v_q,c_needs,'Needs identification',20,20,2),
     (v_assess,v_q,c_obj,'Objection handling',20,20,3),
     (v_assess,v_q,c_neg,'Negotiation',15,15,4),
     (v_assess,(SELECT id FROM skill_competencies WHERE skill_id=v_sales AND name='Closing'),0,'Closing',10,10,5),
     (v_assess,v_q,0,'Communication',15,15,6);
  END IF;
END $$;

-- summary
SELECT 'levels='||(SELECT count(*) FROM skill_levels)
     ||' categories='||(SELECT count(*) FROM skill_categories)
     ||' skills='||(SELECT count(*) FROM skills)
     ||' sales_competencies='||(SELECT count(*) FROM skill_competencies WHERE skill_id=(SELECT id FROM skills WHERE slug='sales'))
     ||' sales_questions='||(SELECT count(*) FROM assessment_questions WHERE assessment_id=(SELECT id FROM assessments WHERE skill_id=(SELECT id FROM skills WHERE slug='sales')))
       AS seed_summary;
