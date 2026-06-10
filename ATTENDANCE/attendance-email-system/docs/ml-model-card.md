# ML Model Card — Detention Risk Classifier

Following the [Google Model Card](https://modelcards.withgoogle.com/about)
lite template.

## Model Details

- **Name:** `detention_v1`
- **Version:** `detention_v1` (see `ml-service/trained_models/metrics.json`)
- **Type:** XGBoost binary classifier (`xgboost.XGBClassifier`)
- **Training date:** 2026-06-08 (current artifact in `ml-service/trained_models/`)
- **Owner:** Varad Gosavi

> **Status note:** The model artifact currently checked into
> `trained_models/detention_v1.joblib` was trained on a **21-row synthetic
> dataset** (16 train / 5 validation) generated for development and testing,
> not on real student attendance. The metrics below are real output from that
> training run, but are not representative of production performance — see
> [Caveats and Limitations](#caveats-and-limitations). Retraining on the
> Phase 8 seeded dataset (200 students × 6 months, see
> [`docs/demo-credentials.md`](demo-credentials.md)) is tracked as Phase 6
> completion work in the [README roadmap](../README.md#roadmap--future-work).

## Intended Use

Predict, for a given student and as-of date, the probability that the
student will fall below the 75% attendance threshold and be placed in
detention. The score is intended to be surfaced to teachers, HODs, and the
principal via the `/detention` dashboard so that **at-risk students can be
contacted and supported before the threshold is crossed** — e.g. a parent
notification email or a counselling referral.

## Out-of-Scope Use

- **Not for academic punishment.** The score is a risk signal for proactive
  outreach, not a basis for disciplinary action, grading, or formal warnings
  on its own.
- **Not for ranking or comparing students** against each other (e.g. class
  leaderboards). The model is calibrated per-student against their own
  history and department average, not as a relative ranking tool.
- **Not a substitute for the official detention determination**, which
  remains the actual attendance percentage against the 75% policy threshold
  computed directly from `attendance` records.

## Factors

The model is evaluated and should be monitored across:

- **Department** — `dept_avg_pct` is an explicit feature, so model behavior
  may vary across the 5 seeded departments (CSE, IT, ENTC, MECH, CIVIL — see
  seeders). Departments with fewer historical records will have noisier
  `subject_variance` and `trend_*` features.
- **Semester / time-of-year** — `trend_7d/14d/30d` features are
  time-window-relative, so predictions early in a semester (little history)
  behave differently from predictions late in a semester (full trend data).

## Metrics

From `ml-service/trained_models/metrics.json` (21-sample synthetic run):

| Metric | Value |
|--------|-------|
| Precision | 1.00 |
| Recall | 1.00 |
| F1 | 1.00 |
| AUC | 1.00 |

**These are not production metrics.** A perfect score on 5 validation
samples is expected for a small, separable synthetic dataset and indicates
overfitting risk, not real-world accuracy. Production-quality precision /
recall / F1 / AUC are **TBD after retraining on the seeded 200-student / 6-month
dataset** (Phase 6 completion).

## Evaluation Data

- **Current artifact:** 5 synthetic validation rows, generated alongside the
  16 synthetic training rows by the model's training pipeline
  (`ml-service/app/routes/train.py`).
- **Planned:** a held-out split of the seeded 200-student, 6-month attendance
  dataset (Step 8), stratified by department and detention outcome.

## Training Data

- **Current artifact:** 16 synthetic rows with features `attendance_pct`,
  `trend_7d`, `trend_14d`, `trend_30d`, `dept_avg_pct`, `subject_variance`,
  `longest_absence_streak` (see `ml-service/app/models/detention_risk.py`),
  and a binary `detained` label (11 detained / 10 not detained).
- **Planned:** the same 7 features computed from the seeded 200-student,
  6-month attendance history across 5 departments.

## Ethical Considerations

- **False-positive cost:** A false positive (flagging a student as
  high-risk who would not actually be detained) results in unnecessary
  parent contact and staff time, and could cause undue stress to a student
  and family. A false negative means a genuinely at-risk student is missed
  until the threshold is already crossed. Given the intended use (early,
  supportive outreach — not punishment), the cost of a false positive is
  considered lower than a false negative, but both should be tracked once
  real predictions are in use.
- **Fairness across departments:** Because `dept_avg_pct` is a feature, the
  model could systematically score students in lower-attendance departments
  as higher-risk even when their individual trend is stable. Per-department
  precision/recall should be monitored once trained on real data (see
  Factors above), and the threshold may need department-specific tuning.
- **Opt-out mechanisms:** Detention status itself is computed independently
  from raw attendance percentages (the 75% policy rule), not from the model.
  The ML score is supplementary; a student/parent can request that staff
  rely on the raw attendance percentage alone and disregard the predicted
  risk score for any individual decision.

## Caveats and Limitations

- The shipped model artifact is trained on **21 synthetic rows** — far too
  few to draw conclusions about real-world precision/recall, and the
  reported AUC of 1.0 is a sign of overfitting on a small, easily-separable
  dataset, not evidence of model quality.
- `trend_7d/14d/30d` and `subject_variance` features return `0.0` or are
  undefined when a student has insufficient history (e.g. early in a
  semester or newly enrolled), which may bias early-semester predictions
  toward lower variance/trend signal than is realistic.
- The model has no explicit fairness constraints; per-department and
  per-semester performance must be validated after retraining before this
  score is relied upon for outreach prioritization.
- `MLPredictionService` (Laravel) treats an unreachable or erroring ML
  service as a hard failure (`RuntimeException`) for the batch — there is no
  degraded "attendance-percentage-only" fallback path in the API today.
