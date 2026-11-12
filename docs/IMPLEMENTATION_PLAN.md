# EduMetric Implementation Plan

> For implementation: execute the tasks sequentially using the executing-plans skill. This document proposes the build; it does not indicate that any feature has been implemented.

**Goal:** Build Carmen Senior High School's teacher evaluation workflow with strand-based access, reliable computation, and authorized reporting.

**Architecture:** Laravel MVC monolith. Eloquent models hold records and domain rules, controllers coordinate requests, and Inertia/Vue pages provide the view layer. Form Requests and policies enforce validation and record access.

**Tech stack:** Existing PHP 8.4, Laravel 13, Fortify, Inertia 3, Vue 3, TypeScript, Wayfinder, existing styling/components, Pest, and a compatible Spatie Laravel Permission release. Target MySQL for school deployment; keep local/test configuration explicit.

**Specification:** [Capstone outline](<labis outline kani na jud.docx>) and the scope, permission matrix, and decisions in [README.MD](../README.MD).

**Status:** The core application workflow is implemented and verified locally. School-approved rubric data, pilot evaluation, and deployment validation remain. SMS notifications have been removed from scope.

**Implementation notes:** The active role name is `Teacher` (faculty). Student enrollment is stored on the account; school year is a validated consecutive-year string on enrollment, assignments, and periods. Each form record is an immutable version once referenced by a period; Duplicate creates the next version. Results are computed from final evaluations instead of stored in a separate aggregate table. Reports provide printable HTML and CSV. The proposed model/file map below remains the original design reference; use the current source and README for the implemented structure.

## Global constraints

- Use Spatie Permission for RBAC and Laravel MVC as the application architecture.
- Retain the existing starter authentication and Inertia/Vue structure; adapt them to school requirements.
- Keep exactly three initial roles: `Admin`, `Faculty`, and `Student`, using guard `web`.
- Enforce permission and record-level eligibility on every protected read, write, export, and download.
- Use the school's approved teacher evaluation instrument; do not reuse the research usability scale as a teacher rubric.
- Preserve submitted evaluations, form versions, and the academic context used at submission.
- Follow `AGENTS.md`, applicable `.ai/rules` if introduced, package versions, sibling conventions, and relevant domain skills before implementation.
- Create Laravel classes through Artisan with `--no-interaction`; use factories and Pest for regression coverage.
- No new architectural base folders or unapproved dependencies. Spatie is the requested RBAC dependency; separate reporting packages require a justified selection.
- Use relative implementation order rather than copying inconsistent source dates into a delivery commitment.

## Proposed design decisions

The user has selected MVC and Spatie RBAC. Keep Vue/Inertia as the view layer because it already exists and works with Laravel controllers; converting to Blade would add migration work. A separate REST backend and SPA would introduce another interface without a requirement in the outline.

Initial proposals are admin-provisioned accounts, validated school-year enrollment, one submission per student/faculty/period, immutable published forms, reviewed result release, and printable HTML plus CSV reporting. Task 1 confirms these with the school before affected behavior is built.

## Review focus

| Condition                                                                 | Required behavior                                                                        | Owning task |
| ------------------------------------------------------------------------- | ---------------------------------------------------------------------------------------- | ----------- |
| Student changes a strand, teacher, period, or form ID in a request        | Server rejects ineligible targets and stores no responses                                | 3 and 5     |
| Duplicate requests arrive together or a deadline passes during submission | Exactly one valid evaluation or a complete rejection; no partial answers                 | 5           |
| A form, enrollment, or assignment changes after submission                | Historical responses and scores retain their original meaning                            | 3, 4, and 6 |
| Faculty changes result/report IDs or a report has few/no responses        | No other faculty's records or respondent identities leak; show no-data states accurately | 6 and 7     |

## Proposed data model

These are implementation targets, not tables that already exist. Verify the actual schema with Boost before creating migrations. Use normal integer foreign keys to match the existing users table and Spatie's published migrations.

| Model and table                                      | Responsibility and important constraints                                                                                                                                                                 |
| ---------------------------------------------------- | -------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `User` / `users`                                     | Existing account plus approved login identifier, mobile number, and active status. Use Spatie role relationships; no `role` column                                                                       |
| Spatie package tables                                | `roles`, `permissions`, `model_has_roles`, `model_has_permissions`, `role_has_permissions`, from package migrations                                                                                      |
| `Student` / `students`                               | Unique `user_id` and school-issued student identifier                                                                                                                                                    |
| `Faculty` / `faculty`                                | Unique `user_id` and employee identifier; explicitly set the table name if required by Eloquent conventions                                                                                              |
| `Strand` / `strands`                                 | Unique code, name, and active status; initial school-confirmed STEM, ABM, HUMSS, TVL records                                                                                                             |
| `SchoolYear` / `school_years`                        | Unique label and valid date boundaries                                                                                                                                                                   |
| `StudentEnrollment` / `student_enrollments`          | Student, school year, strand, grade, section, active status. Proposed one enrollment per student/year unless transfers require history                                                                   |
| `TeacherAssignment` / `teacher_assignments`          | Faculty, strand, grade, section, school year, and active status. Retain referenced assignments rather than deleting them                                                                                 |
| `EvaluationForm` / `evaluation_forms`                | Logical form name and archive status                                                                                                                                                                     |
| `EvaluationFormVersion` / `evaluation_form_versions` | Form, version number, status, rating scale, weighting rules, precision, and performance bands; unique form/version                                                                                       |
| `EvaluationCriterion` / `evaluation_criteria`        | Form version, stable question order, prompt, description, positive weight, and required flag                                                                                                             |
| `EvaluationPeriod` / `evaluation_periods`            | School year, frozen form version, opening/closing times, lifecycle status, and result-release timestamp                                                                                                  |
| `Evaluation` / `evaluations`                         | Student, faculty, period, enrollment and assignment references, form version, submitted timestamp, score, and academic context snapshot. Unique student/faculty/period under the proposed duplicate rule |
| `EvaluationResponse` / `evaluation_responses`        | Evaluation, criterion, validated rating, optional comment. Unique evaluation/criterion                                                                                                                   |
| `EvaluationResult` / `evaluation_results`            | Rebuildable aggregate by faculty/period, response count, criterion summary, overall score, performance level, and computation metadata. Unique faculty/period                                            |
| `Report` / `reports`                                 | Requesting admin, type, validated filters, generation time, and private stored-file path when applicable                                                                                                 |

Do not cascade-delete submitted evaluations when users, strands, or forms are retired. Store decimal values for ratings/weights/scores as appropriate, define rounding explicitly, and index eligibility/report filters. Record transfer and assignment edits without reinterpreting existing evaluations. If history requires multiple enrollments per year, replace the proposed enrollment uniqueness with effective dates before migration design is finalized.

## MVC file map

Paths below describe ownership for later implementation. Existing files are modified; absent files are created in ordinary Laravel directories. Generate migrations with timestamped names through Artisan. A file listed in a task belongs to that task unless a later task explicitly extends it.

| Area                                     | Target paths                                                                                                                                                                                                   |
| ---------------------------------------- | -------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| Authentication and shared access         | `app/Models/User.php`, `app/Providers/FortifyServiceProvider.php`, `config/fortify.php`, `bootstrap/app.php`, `app/Http/Middleware/EnsureAccountIsActive.php`, `app/Http/Middleware/HandleInertiaRequests.php` |
| RBAC                                     | `config/permission.php`, package migrations, `database/seeders/RolePermissionSeeder.php`, `database/seeders/DatabaseSeeder.php`                                                                                |
| Academic records and evaluation entities | Models named in the data model, matching migrations, `database/factories`, and reference/demo seeders                                                                                                          |
| Controllers                              | `app/Http/Controllers/Admin`, `app/Http/Controllers/Faculty`, `app/Http/Controllers/Student`, and `app/Http/Controllers/DashboardController.php`                                                               |
| Requests and policies                    | Corresponding classes in `app/Http/Requests` and `app/Policies`                                                                                                                                                |
| Views                                    | `resources/js/pages/admin`, `resources/js/pages/faculty`, `resources/js/pages/student`; reuse `resources/js/components` and existing layouts                                                                   |
| Route and view types                     | `routes/web.php`, existing `resources/js/types`, generated Wayfinder files under `resources/js/actions` and `resources/js/routes`                                                                              |
| Verification                             | Feature tests in `tests/Feature`; pure scoring tests in `tests/Unit` only if scoring is extracted into a framework-independent helper                                                                          |

Controllers use explicit return types: `Inertia\Response` for pages, `Illuminate\Http\RedirectResponse` for successful mutations, and the appropriate streamed/file response for downloads. Pass validated input, use dependency injection where needed, and authorize through policies or `Gate::authorize` rather than assuming the existing empty base controller exposes authorization helpers.

## Task 1: Confirm the school workflow and scoring contract

**Depends on:** None. **Files:** Update the decisions in `README.MD` and this plan when confirmed. No product code.

- [ ] Obtain the actual teacher questionnaire, question groups, scales, weights, required-answer rules, performance labels, thresholds, and rounding convention from the school.
- [ ] Confirm school name, academic calendar, grade/section relevance, strand selection, enrollment transfers, subject-specific assignments, and the one-submission rule.
- [ ] Confirm username/school-ID versus email login, account provisioning, recovery, and email verification availability for students.
- [ ] Confirm who may inspect raw responses, whether feedback is anonymous to faculty, minimum cohort size, release timing, and comment moderation requirements.
- [ ] Confirm printable/download formats and pilot questionnaire handling. Do not add a survey module by inference.
- [ ] Resolve inconsistent usability-scale ranges and source captions without importing social-work workflows or illustrative mockup scores.

**Acceptance:** Approved sample input and expected scores, role boundaries, and workflows are recorded. If an answer remains unresolved, the dependent feature remains a proposal; unrelated tasks may proceed.

## Task 2: Establish authentication, account lifecycle, and Spatie RBAC

**Depends on:** Task 1's account decisions.

**Files:** Shared-access and RBAC paths above; `app/Http/Controllers/Admin/UserController.php`, `app/Http/Requests/Admin/StoreUserRequest.php`, `UpdateUserRequest.php`, `app/Policies/UserPolicy.php`, `resources/js/pages/admin/users/Index.vue`, `Form.vue`, `resources/js/pages/auth/Login.vue`, existing auth actions/views as needed, and `tests/Feature/RoleAccessTest.php`, `UserManagementTest.php`, plus affected auth tests.

**Interface:** `User` exposes Spatie permission checks using `web`; `dashboard` resolves the correct role view. Protected requests reject inactive users, including previously authenticated sessions. `UserPolicy` controls account changes and role assignment.

- [ ] Write failing feature tests for each role's dashboard, permission denial, inactive login/session denial, and unauthorized role changes through profile/registration payloads.
- [ ] Confirm current versions. Install the requested compatible `spatie/laravel-permission` release, inspect `composer show spatie/laravel-permission`, and consult its version's docs.
- [ ] Publish package configuration/migrations using its documented provider command with `--no-interaction`; add `HasRoles` without a parallel custom role column. Seed the exact README permission matrix idempotently and reset package permission cache appropriately.
- [ ] Adapt Fortify to the confirmed identifier and school provisioning rules. Proposed default: disable public registration, let Admin create accounts, and retain password reset and existing security features where compatible.
- [ ] Add active-account enforcement, scoped user CRUD, and controlled role assignment; preserve the last active Admin and prevent users granting themselves privileges.
- [ ] Share a minimal list of effective permissions with Inertia and implement role dashboards using server permissions. A role has no implicit exemption from policy checks.
- [ ] Replace starter-account seeding with environment-limited demo accounts and an explicit first-admin provisioning procedure without committed credentials.
- [ ] Add logout confirmation in the shared layout; retain Fortify's session-ending POST operation and CSRF protection.
- [ ] Run affected auth tests and `php artisan test --compact tests/Feature/RoleAccessTest.php tests/Feature/UserManagementTest.php`; expected result: all selected tests pass.

**Acceptance:** Three roles have the documented capabilities, direct URL access is protected, inactive users lose access, and ordinary users cannot promote themselves.

## Task 3: Manage academic records and enforce teacher eligibility

**Depends on:** Task 2 and confirmed enrollment rules.

**Files:** `Student.php`, `Faculty.php`, `Strand.php`, `SchoolYear.php`, `StudentEnrollment.php`, `TeacherAssignment.php` in `app/Models`; corresponding migrations/factories; `database/seeders/StrandSeeder.php`; Admin controllers `StudentController.php`, `FacultyController.php`, `StrandController.php`, `SchoolYearController.php`, `TeacherAssignmentController.php`; matching requests/policies; `app/Http/Controllers/Student/StrandSelectionController.php`; academic administration pages and `resources/js/pages/student/StrandSelection.vue`; `tests/Feature/AcademicManagementTest.php`, `TeacherEligibilityTest.php`.

**Interface:** `Student::eligibleTeacherAssignments(EvaluationPeriod $period)` returns an Eloquent builder limited by active enrollment, permitted strand, matching school year, and confirmed grade/section rules. Task 4 supplies `EvaluationPeriod`; until then test academic matching through assignment/enrollment relationships. `student.strand.store` accepts a strand selection but validates it against stored enrollment.

- [ ] Write failing tests for authorized academic CRUD and denied Faculty/Student management access; cover duplicate school identifiers and invalid assignments.
- [ ] Inspect the schema, generate models/migrations/factories, and add foreign keys, appropriate uniqueness, and filter indexes. Seed school-confirmed strands without assumed real student records.
- [ ] Implement academic administration and enrollment updates with preserved history. Deactivate referenced records instead of deleting evaluation context.
- [ ] Implement strand selection after student login using permitted enrollment, never an unrestricted list that grants access by itself.
- [ ] Test cross-strand, wrong-year, inactive faculty/assignment/enrollment, grade/section mismatches where applicable, and transfers. Complete period-dependent eligibility tests when Task 4 lands.
- [ ] Run `php artisan test --compact tests/Feature/AcademicManagementTest.php tests/Feature/TeacherEligibilityTest.php`; expected result: all selected tests pass.

**Acceptance:** Admin can maintain school records; students see only teachers they are eligible to evaluate. Multi-strand faculty appear correctly for eligible students.

## Task 4: Build versioned forms and scheduled evaluation periods

**Depends on:** Tasks 1–3.

**Files:** Form/criterion/period models and matching migrations/factories; `app/Http/Controllers/Admin/EvaluationFormController.php`, `EvaluationPeriodController.php`; matching Form Requests and policies; `resources/js/pages/admin/forms/Index.vue`, `Edit.vue`, `resources/js/pages/admin/periods/Index.vue`, `Edit.vue`; `tests/Feature/EvaluationFormTest.php`, `EvaluationPeriodTest.php`.

**Interface:** `EvaluationPeriod::isOpen(): bool` evaluates lifecycle plus the configured time window. A period references exactly one published form version. A published `EvaluationFormVersion` and its criteria/scoring metadata cannot be edited in place. Proposed period lifecycle: draft → open → closed → released; dates still enforce availability.

- [ ] Write failing tests for question validation, positive weights, valid rating scales, unique ordering, and invalid opening/closing times.
- [ ] Implement draft editing, publication, and cloning to a new version for subsequent revisions. Archive referenced forms; prevent destructive edits to published criteria.
- [ ] Implement period CRUD and transitions, using the approved school timezone for display and consistent timestamp storage. Reject requests before opening and at/after closing; proposed interval is `opens_at <= now < closes_at`.
- [ ] Keep a period's form version and school year fixed once opened. Gate reopening/revisions behind a separately confirmed workflow rather than silently changing past submissions.
- [ ] Test publication immutability, deadline boundaries using controlled time, wrong-year eligibility, and period changes with existing evaluations.
- [ ] Run `php artisan test --compact tests/Feature/EvaluationFormTest.php tests/Feature/EvaluationPeriodTest.php tests/Feature/TeacherEligibilityTest.php`; expected result: all selected tests pass.

**Acceptance:** Admin can publish approved forms and schedule evaluations; subsequent edits cannot reinterpret submitted ratings.

## Task 5: Deliver the student evaluation workflow

**Depends on:** Tasks 2–4.

**Files:** `app/Models/Evaluation.php`, `EvaluationResponse.php`, matching migrations/factories; `app/Http/Controllers/Student/EvaluationController.php`, `app/Http/Requests/Student/StoreEvaluationRequest.php`, `app/Policies/EvaluationPolicy.php`; `resources/js/pages/student/evaluations/Index.vue`, `Create.vue`, `Submitted.vue`; `tests/Feature/StudentEvaluationTest.php`.

**Interface:** `EvaluationController::index(Request $request): Response`, `create(Request $request, TeacherAssignment $teacherAssignment, EvaluationPeriod $period): Response`, and `store(StoreEvaluationRequest $request): RedirectResponse`. The server derives student identity, faculty, form version, and criteria from authenticated context and authorized records. Client scores and student IDs are never authoritative.

- [ ] Write failing feature tests for eligible form access, successful submission, required answers, invalid ratings, foreign/missing/duplicate criteria, forbidden targets, and HTML-like comments displayed safely.
- [ ] Implement eligible-teacher listing, form completion, confirmation, and submitted status using existing Vue components, server validation errors, and Wayfinder routes.
- [ ] Validate the complete answer set against the period's frozen version. Proposed default: all scored questions are required; confirm any optional-answer rule in Task 1 before enabling it.
- [ ] Save evaluation and answers inside a transaction; lock/check the period and relevant eligibility records as needed and recheck availability during the write. Add the database unique constraint for student/faculty/period.
- [ ] Handle duplicate-key races as a clear already-submitted outcome; a simultaneous submit must not create duplicate or partial answers. Closed periods and missing required answers create no evaluation.
- [ ] Freeze final submissions under the initial rule; neither Student nor Admin edits submitted ratings through ordinary CRUD. Any correction/withdrawal workflow requires explicit school rules.
- [ ] Test deadline races, repeat submissions from different assignment links for the same teacher, and atomic rollback. Exercise concurrency/uniqueness against the deployment database where SQLite cannot reproduce MySQL locking.
- [ ] Run `php artisan test --compact tests/Feature/StudentEvaluationTest.php`; expected result: all selected tests pass.

**Acceptance:** An eligible student can complete one valid evaluation per faculty/period; tampered, late, incomplete, and duplicate requests cannot corrupt results.

## Task 6: Compute results and release faculty feedback

**Depends on:** Tasks 1, 4, and 5.

**Files:** `app/Models/EvaluationResult.php` and migration/factory; `app/Models/Evaluation.php`; `app/Http/Controllers/Admin/EvaluationResultController.php`, `app/Http/Controllers/Faculty/EvaluationResultController.php`, `app/Policies/EvaluationResultPolicy.php`; result pages in `resources/js/pages/admin/results` and `resources/js/pages/faculty/results`; `tests/Feature/EvaluationScoringTest.php`, `FacultyResultAccessTest.php`.

**Interface:** `Evaluation::calculateScore(): string` returns a decimal score using the frozen rubric. `EvaluationResult::rebuildFor(EvaluationPeriod $period, Faculty $faculty): self` recomputes the aggregate from final responses idempotently. `EvaluationResultPolicy::view(User $user, EvaluationResult $result): bool` checks permission, ownership, and release rules.

- [ ] Write calculation tests from school-approved examples before implementing the algorithm. Keep the approved inputs and expected decimal values in fixtures.
- [ ] If the school approves simple question weighting, use `sum(rating × weight) / sum(weight)` for each complete evaluation and average equally weighted evaluation scores across respondents. Criterion averages, group weighting, optional responses, and performance thresholds must follow the approved instrument rather than this proposal by default.
- [ ] Validate nonzero total weights, retain sufficient calculation precision, and round only at the agreed stage. A zero-response aggregate has a count of zero and no score/performance label.
- [ ] Compute submission totals on the server and rebuild aggregates from committed responses before release/reporting. Serialize rebuilds per faculty/period or use consistent snapshots so concurrent submissions cannot leave a stale published result.
- [ ] Add Admin monitoring and explicit result release. Faculty receives only its own released summaries and permitted feedback; exclude student IDs/names and individual response records from faculty Inertia props.
- [ ] Apply the confirmed minimum-response/comment rules. Avoid identifying students through small cohorts or metadata where the school's confidentiality policy forbids it.
- [ ] Test weights, threshold boundaries, rounding, no-data, historical form changes, repeated rebuilds, concurrent updates, unreleased results, another faculty's IDs, and respondent-field absence.
- [ ] Run `php artisan test --compact tests/Feature/EvaluationScoringTest.php tests/Feature/FacultyResultAccessTest.php`; expected result: all selected tests pass.

**Acceptance:** Results match signed-off calculations, remain reproducible, and expose only authorized faculty data after release.

## Task 7: Provide reports, summaries, charts, and downloads

**Depends on:** Task 6.

**Files:** `app/Models/Report.php` and migration/factory; `app/Http/Controllers/Admin/ReportController.php`, `app/Http/Requests/Admin/GenerateReportRequest.php`, `app/Policies/ReportPolicy.php`; `resources/js/pages/admin/reports/Index.vue`, `Show.vue`; `tests/Feature/EvaluationReportTest.php`.

**Interface:** `ReportController::store(GenerateReportRequest $request): RedirectResponse` persists a generation request; `show(Request $request, Report $report): Response` returns the authorized printable report; `download(Request $request, Report $report)` returns a typed CSV stream or authorized stored file. All use the same scoring source as Task 6.

- [ ] Write failing tests for Admin-only report generation/download, invalid filters, empty periods, released versus provisional labeling, and consistency with scored results.
- [ ] Build faculty/period/school-year/strand filters, criterion summaries, response counts, and accessible charts/trends. Label the exact cohort and period; do not combine incompatible form versions as equivalent scores.
- [ ] Clarify strand-filtered semantics: proposed reports aggregate evaluations from students in that strand using the stored submission context, so multi-strand faculty totals are not mislabelled. Test this separately from overall faculty totals.
- [ ] Implement printable HTML and CSV first, including school identity, selected filters, generation timestamp, and provisional/released status. Use existing UI/SVG capabilities unless a chart dependency is approved.
- [ ] Authorize every report page and download; keep stored artifacts private and reject guessed paths/IDs. Escape feedback and protect CSV text fields against spreadsheet formula interpretation.
- [ ] Include report metadata and no-data states without exposing individual student responses. Add PDF/XLSX only after confirming a requirement and compatible package.
- [ ] Run `php artisan test --compact tests/Feature/EvaluationReportTest.php`; expected result: all selected tests pass. Manually inspect print layout and downloaded sample content.

**Acceptance:** Authorized Admin users can view, print, and download consistent reports and understand response counts and report scope.

## Task 8: Run the school pilot and prepare deployment

**Depends on:** Tasks 2–7.

**Files:** Existing tests plus `tests/Feature/EvaluationWorkflowTest.php`; update this plan and README setup/status with verified behavior. Deployment configuration changes depend on the selected hosting environment.

- [ ] Add an end-to-end feature scenario: Admin configures assignments/form/period → Student selects an authorized strand and submits → Admin reviews/releases → Faculty views own results → Admin prints/downloads.
- [ ] Run affected tests during each task, then perform the full existing checks below. Fix failures before pilot delivery; do not delete starter tests to force a pass.
- [ ] Manually verify desktop/mobile flows, keyboard access, field errors, charts/no-data states, logout confirmation, print layout, and role-specific navigation using representative accounts.
- [ ] Use synthetic records to exercise approximately 1,500 students and 66 teachers. Check paginated administration, eligibility query count, report queries, concurrent submissions against the deployment database; agree measurable performance targets with the school.
- [ ] Conduct the separate usability questionnaire with Admin, Faculty, and Student respondents. Obtain the corrected four-point usability interpretation, summarize frequency/mean/percentage results, and record feedback without mixing it with teacher scores. Build in-app survey management only if separately requested.
- [ ] Configure production database, HTTPS, mail/recovery, private storage, time settings, and `APP_DEBUG=false`. Provision the first Admin securely and remove demo accounts/data.
- [ ] Verify backup and restore on staging, protect evaluation records, rehearse migrations and rollback/recovery, and obtain school acceptance of the supported workflows. Use the Laravel Cloud skill only if Laravel Cloud is selected.

Implementation verification commands:

```bash
vendor/bin/pint --dirty --format agent
php artisan test --compact
composer run types:check
npm run check
npm run types:check
npm run build
```

Run Pint after PHP edits. Run the narrowest affected tests first; full checks belong to the final integration gate. The commands above are future implementation requirements and have not been run as evidence for this documentation-only task.

**Acceptance:** The workflow passes automated and manual verification, the pilot results are available, and the school can operate accounts, periods, reports, and backups.

## Milestones and completion criteria

| Milestone                 | Tasks | Demonstrable result                                                                   |
| ------------------------- | ----- | ------------------------------------------------------------------------------------- |
| Rules agreed              | 1     | School instrument, access rules, and acceptance examples confirmed                    |
| Access and academic setup | 2–3   | Secure dashboards and accurate student-to-teacher eligibility                         |
| Evaluation collection     | 4–5   | Approved scheduled forms and valid, unique student submissions                        |
| Evaluation output         | 6–7   | Verified computations, controlled faculty release, and printable/downloadable reports |
| Pilot readiness           | 8     | Tested full workflow, usability findings, and deployment preparation                  |

After each implementation task, review its acceptance criteria and checks before committing a focused change when a writable Git checkout is available. Do not mark a milestone complete based only on scaffolding or the existence of files. Update README status only after the corresponding behavior is verified.
