# SRC Executive User Manual

## 1. System Overview

The SRC Decision Support System (DSS) helps officers evaluate project proposals in a structured, transparent way.

It supports two main goals:

| Function | Purpose |
| --- | --- |
| Resource Optimization | Matches project proposals against the semester budget and volunteer-hour limits so executives can see which projects fit current capacity. |
| AI Risk Assessment | Reviews pending proposals for common operational risk signals before the final optimization run. |

In simple terms, the DSS helps the SRC:

1. Collect project proposals in one place.
2. Compare proposals fairly using the approved weighting model.
3. Identify which projects should be accepted, deferred, or rejected.
4. Keep an audit trail of every official optimization run.

## 2. Role-Specific Guides

### The Projects Coordinator

#### Submit a New Proposal

1. Sign in and open `Submit Proposal`.
2. Enter the project title.
3. Enter the required budget.
4. Enter the estimated volunteer hours.
5. Enter the expected student reach.
6. Enter the expected implementation period in weeks.
7. Select `Submit for Optimization` to place the proposal in the decision queue.

#### Save a Draft

1. Open `Submit Proposal`.
2. Complete the form with as much information as available.
3. Select `Save as Draft`.
4. Open `Proposal Drafts` later when you are ready to submit it officially.

#### Submit a Saved Draft

1. Open `Proposal Drafts`.
2. Review the saved record.
3. Select `Submit to Queue`.
4. Confirm that the project now moves into the optimization workflow.

#### What the Rollover Queue Means

The `Rollover Queue` lists projects marked as `Deferred`.

| Status | Meaning |
| --- | --- |
| Deferred | The project showed merit, but current budget or volunteer-hour limits could not accommodate it during the latest optimization run. |
| Rollover Queue | A holding area for strong projects that may be reconsidered in a later semester or after constraints change. |

### The Financial Secretary

#### Set Semester Budget and Hour Constraints

1. Sign in and open `Constraints & Weights`.
2. Review the historical table at the top of the page.
3. In `Define Semester Capacity & PIS Weights`, enter the official academic term.
4. Enter the maximum available budget for the term.
5. Enter the maximum volunteer hours available for the term.
6. Set the reach and speed weights.
7. Confirm that the two weights add up to `1.0`.
8. Select `Save Semester Constraints`.

#### Submit a GDSS Vote

1. Stay on `Constraints & Weights`.
2. Go to `Group Decision Support System (GDSS)`.
3. Enter the target academic term.
4. Enter your reach preference.
5. Enter your speed preference.
6. Confirm that both values add up to `1.0`.
7. Select `Submit My Vote`.

#### Read the Budget Analytics Charts

Use `Budget Analytics` to understand current spending pressure.

| Display | How to Read It |
| --- | --- |
| Total Semester Budget | The full budget ceiling currently active for the term. |
| Budget Committed | The amount already tied to accepted projects. |
| Remaining Budget | The amount still available for future approvals. |
| Budget Breakdown by Status | A table showing how much money is currently associated with accepted, pending, rejected, and deferred projects. |
| Budget Distribution Chart | A visual comparison of spending volume across project statuses. |

If `Budget Committed` is close to `Total Semester Budget`, new proposals are more likely to be deferred or rejected unless constraints are updated.

### The Executive Board

#### Use the Sandbox Simulator

The Sandbox Simulator is a safe testing environment. It does not change official records.

1. Sign in and open `Sandbox Simulator`.
2. Enter a simulated budget amount.
3. Enter simulated volunteer hours.
4. Set simulated reach and speed weights.
5. Confirm the weights add up to `1.0`.
6. Select `Run Sandbox Simulation`.
7. Review the simulated accepted, rejected, and deferred project lists.

Use this page before a formal decision meeting when the board wants to test alternative scenarios.

#### Interpret the AI Expert System Warnings

1. Open `Optimization Console`.
2. Select `Initialize Expert System Analysis`.
3. Review the warning notes shown beside each pending proposal.

These warnings are advisory, not final decisions.

| Warning Type | Meaning |
| --- | --- |
| Budget concern | The proposal may be expensive compared with current constraints. |
| Hours concern | The proposal may be difficult to support with available volunteer capacity. |
| Timing concern | The project may be too slow to implement within the intended period. |
| Reach concern | The student benefit may be lower than expected compared with other proposals. |

Executives should use these warnings to guide discussion before running the official optimization engine.

#### Read the Final Knapsack Optimization Audit Report

1. Open `Optimization Console`.
2. Select `Run Knapsack Optimization Engine` only when the board is ready for an official result.
3. Review the `Current Optimization Report`.
4. Check the `Historical Optimization Runs` section for previous official records.

Focus on the following sections in the report:

| Report Section | Meaning |
| --- | --- |
| Resource Utilization Summary | Shows how much budget and volunteer capacity were consumed by accepted projects. |
| Accepted Projects | Projects selected within the current resource limits. |
| Rejected Projects | Projects not selected by the optimization outcome. |
| Deferred Projects (Rollover Queue) | Projects with merit that did not fit current resource capacity. |
| Pending Projects | Projects that still await a final outcome, if any remain. |

If the report displays an algorithmic notice about identical scores, the system used a deterministic tie-break process to keep the result consistent and fair.

### The Administrator

#### Daily System Monitoring

1. Sign in and open `System Dashboard`.
2. Review:
   - total active users
   - pending proposals
   - total optimization runs
   - system status
3. Confirm that the database connection is shown as connected.
4. Review the project distribution chart for unusual backlog growth.
5. Export project or user records when management reporting is required.

#### Create New Executive Accounts Safely

1. Open `System Configuration`.
2. Go to `User Management`.
3. Enter the new username.
4. Create a strong temporary password.
5. Choose the correct role.
6. Select `Create User`.
7. Instruct the officer to keep credentials confidential and change their password through the approved administrative process when available.

Use role assignment carefully:

| Role | Recommended Use |
| --- | --- |
| Projects Coordinator | Proposal intake and draft management |
| Financial Secretary | Budget and weighting administration |
| Executive Board | Optimization, sandbox analysis, and final decision review |
| Admin | System maintenance and user administration only |

#### When to Run Backups or Project Resets

Run a database backup:

1. Before any major executive review session.
2. Before deleting projects or purging audit logs.
3. Before semester-end archival work.
4. After a significant set of new proposals is entered.

Run a project reset only when the board has formally decided to reopen the project cycle.

**Warning:** `Reset All Projects to Pending` removes the current decision status from all projects.

**Warning:** `Truncate Projects Table` permanently deletes all project records.

**Warning:** `Purge All Logs` permanently removes audit history and should only be used with written administrative approval.

## 3. Troubleshooting & Support

### Why Is My Project Deferred?

Common reasons include:

| Reason | Explanation |
| --- | --- |
| Budget ceiling reached | The project could not fit inside the remaining semester budget. |
| Volunteer-hour ceiling reached | The project required more human effort than the current capacity allowed. |
| Higher-ranked proposals consumed resources first | Other projects achieved stronger optimization scores under the approved weighting model. |
| Constraint changes are needed | The proposal may become viable if the board later increases budget or volunteer-hour limits. |

Recommended action:

1. Review the `Rollover Queue`.
2. Ask the Executive Board to compare the project against the latest audit report.
3. Reconsider the project during the next planning cycle or after updated constraints are approved.

### What Does "Network Error" Mean?

This message usually means the browser could not complete a request to the server.

Recommended action:

1. Confirm that the internet or local network connection is active.
2. Refresh the page and try again.
3. Sign out and sign back in if the session may have expired.
4. Contact the Administrator if the issue affects multiple users or continues after refresh.

### What If the Charts or Reports Do Not Load Correctly?

1. Refresh the page.
2. Try the same page on another browser or device.
3. Confirm that the system is not in maintenance mode.
4. Report the exact page name and the time of the issue to the Administrator.

### What If a Form Does Not Save?

1. Confirm that all required fields are completed.
2. Check that numbers were entered in the correct format.
3. For weights, confirm the values total `1.0`.
4. Submit the form again after refreshing the page.

## 4. Support Escalation

Use the following escalation guide:

| Situation | First Contact | Escalation |
| --- | --- | --- |
| Proposal workflow question | Projects Coordinator | Executive Board |
| Budget or weighting issue | Financial Secretary | Executive Board |
| Optimization interpretation issue | Executive Board | Administrator |
| Login, backup, export, or system failure | Administrator | Technical support team |

## 5. Good Operating Practice

1. Save entries carefully and review numeric values before submission.
2. Run sandbox analysis before any major official optimization session.
3. Keep all officer credentials confidential.
4. Back up the system before destructive maintenance actions.
5. Use the historical audit reports as the official record of prior optimization decisions.
