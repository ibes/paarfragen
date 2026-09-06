-- Seed questions for exploration mode. Hard-coded to SQLite syntax —
-- this repo's only DB dialect for this slice, see
-- specs/2026-09-06-slice-2-questions-feedback-persistence.md.
-- `created_at` is left out on purpose: the column defaults to
-- CURRENT_TIMESTAMP (CreateQuestionsTable).
INSERT INTO questions (id, text, source) VALUES
  ('01a076b0-cecb-7e19-b6a4-d4964e4a81a1', 'What is something I did recently that made you feel loved?', '{"type":"seed"}'),
  ('01a076b0-cecd-72e0-9979-9f4f4c2dbb4f', 'What is a small habit of mine that you secretly love?', '{"type":"seed"}'),
  ('01a076b0-cecd-7354-9979-9f4f4c5e1bec', 'If we could relive one day together, which one would you pick?', '{"type":"seed"}'),
  ('01a076b0-cecd-73ac-9979-9f4f4c6c48af', 'What is something you have never told me but wish I knew?', '{"type":"seed"}'),
  ('01a076b0-cecd-73c0-9979-9f4f4d245759', 'What does a perfect ordinary Sunday look like to you?', '{"type":"seed"}'),
  ('01a076b0-cecd-73cc-9979-9f4f4d497803', 'What is one thing you would like us to try together this year?', '{"type":"seed"}'),
  ('01a076b0-cecd-73d4-9979-9f4f4dbf25ef', 'When do you feel most understood by me?', '{"type":"seed"}'),
  ('01a076b0-cecd-73e0-9979-9f4f4e611f0d', 'What is a disagreement we had that you now see differently?', '{"type":"seed"}');
