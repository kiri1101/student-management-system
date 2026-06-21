# Administrator Guide

A plain-language guide to running SchuLyf as a **system administrator**. It covers everything you do from the moment you sign in: managing staff accounts, keeping the system's reference data and tuition fees up to date, and reviewing the audit log of who did what.

This guide describes only what is on screen. For the design and rules behind staff accounts, see the [Admin user management module](../modules/admin-user-management.md); for how the audit log and sign-in security work, see [Security](../security.md).

---

## Who this guide is for

You are an **Administrator** if your account has the Administrator role. Administrators are the people who set SchuLyf up and keep it running. You can:

- Create, edit, deactivate, and restore the **staff and admin accounts** that run the school (lecturers, accountants, Student Affairs Officers, and other administrators).
- Bulk-import staff from a spreadsheet.
- Maintain the **reference data** the application form depends on — departments, degree programmes, document types, and per-level document requirements.
- Configure **tuition fee schedules** and their installment deadlines.
- Review the **audit log** — a permanent, read-only record of every important action across the system.

You do **not** create applicants or students here. Applicants sign up for themselves, and students are admitted by a Student Affairs Officer. Your job is the staff and the rules of the system, not the student records.

---

## Getting in

### Your account

Administrator accounts are created in one of two ways:

- The very first administrator account is set up when the system is installed.
- Every administrator after that is created by another administrator, using the same **New user** process described later in this guide.

Either way, you receive an email containing a **single-use link to set your password**. No one — not even another administrator — sets your password for you. Open the email, click the link, choose your password, and you are signed in. Your account is already verified, so there is no separate "verify your email" step.

If your invitation link has expired or you never received it, ask another administrator to **resend your invite** (this creates a fresh link and cancels the old one), or use **Forgot password?** on the sign-in page.

### Signing in

1. Go to the SchuLyf sign-in page.
2. In the **Username** field, type your **email address** or your **employee ID** (whichever your account was set up with). The small information icon next to the field reminds you which identifiers are accepted.
3. Enter your **Password**.
4. Tick **Remember me** if you are on your own device.
5. Click **Log in**.

You land on the **Admin Dashboard**.

If you forget your password, click **Forgot password?** and follow the emailed link to reset it. You can turn on **two-factor authentication** later from your Security settings (see [Staff settings](#staff-settings)).

---

## What you can do — the dashboard

The **Admin Dashboard** is your home screen and gives you a quick read on the whole system.

- **Summary cards** at the top show the live totals for **Users**, **Applications**, and **Student profiles**.
- **Users by role** lists how many accounts exist for each role.
- **Applications by status** shows where admission applications currently sit.
- **Recent admissions** is a table of the latest admitted students (their matricule — the unique student identifier assigned at admission — name, programme, academic year, and enrolment date).
- **Quick links** at the bottom jump you straight to **Manage users**, **Reference data**, and the **Audit log**.

An **Open audit log** button sits in the top-right of the dashboard (and again on the Audit log quick-link card) so you can open the full activity record at any time.

The left-hand navigation menu is how you move between the main areas: **Users**, **Reference data**, **Fees**, and your account **Settings**.

---

## Task walkthroughs

### Create a single staff or admin user

Use this when you need to add one lecturer, accountant, Student Affairs Officer (SAO), or administrator.

1. From the dashboard quick link **Manage users** (or the **Users** menu item), open the **Users** page.
2. Click **New user**.
3. Fill in the form:
   - **Full name** — the person's name.
   - **Email** — their work email. This is where their setup link is sent.
   - **Employee ID** (optional) — if you provide one, the person can also sign in with this ID instead of their email.
   - **Role** — choose **Lecturer**, **Accountant**, **SAO**, or **Admin**. (Applicant and Student are intentionally not offered here.)
4. Depending on the role you picked, an extra section appears:
   - **Lecturer profile** — choose a **Department**, and optionally a **Specialization** and a **Hired on** date.
   - **Accountant profile** — optionally a **Bank desk** and a **Cashier window**.
   - **SAO profile** — optionally a **Scope** (for example, "Admissions" or "Records").
   - Administrators have no extra profile fields.
5. Click **Create user**.

**How you can tell it worked:** you return to the Users list and the new person appears with a **Pending invite** status. Behind the scenes they receive an email with a single-use link to set their own password. You never see or set their password.

**Common problems and what to do:**
- *"The email has already been taken."* — An account with that email already exists. Search the Users list for it; you may only need to edit or restore it.
- *The person says they got no email.* — Open their record and use **Resend invite** (see below). This sends a fresh link and invalidates the previous one. Confirm their email address is correct.

### Edit a user

1. On the **Users** page, find the person (use the search box and filters), then click the **Edit** (pencil) button on their row.
2. You can change their **Full name** and **Employee ID**, and any fields specific to their role.
3. The **Email** is shown but cannot be changed here — the user manages their own email through the password-reset and verification process.
4. Click **Save changes**.

The page also has an **Account actions** card with four buttons:
- **Resend invite** — sends a new password-setup link and cancels the old one. Useful when an invite was lost or expired. (Disabled for deactivated users.)
- **Change role** — see the next section.
- **View audit log** — opens the audit log filtered to just this person (everything they did and everything done to their account).
- **Deactivate** — see [Deactivate or restore a user](#deactivate-or-restore-a-user).

### Change a user's role

Use this to move someone between staff roles — for example, an accountant who becomes an SAO.

1. Open the person's record with **Edit**.
2. In the **Account actions** card, click **Change role**.
3. In the **Change role** dialog, pick the **New role**.
4. Fill in the new role's profile fields (department, scope, and so on) shown below the selector.
5. Click **Update role**.

**Important:** reassigning a role **detaches the previous role and removes its profile**. If you switch an accountant to a lecturer, the accountant-specific details are removed and you start the lecturer profile fresh. You cannot change your own role.

### Bulk-import staff from a CSV

Use this to onboard many staff at once. It is a safe two-step process: validate the file first, then confirm only the rows that pass.

1. On the **Users** page, click **Import CSV**. This opens the **Bulk staff import** page.
2. (First time) Click **Download template** to get a correctly formatted spreadsheet to fill in.
3. Prepare your file using the **Expected columns** shown on the page:
   - **name** — required.
   - **email** — required.
   - **role** — required; one of lecturer, accountant, sao, admin.
   - **employee_id** — optional.
   - **department_code** — required for lecturers.
   - **specialization**, **hired_at** (date as YYYY-MM-DD), **bank_desk**, **cashier_window**, **scope** — optional.
4. Click **Choose CSV** and select your file, then click **Validate / Preview**.
5. Review the **Preview**. Each row is marked **OK** or **Error**, with a summary of how many are valid and how many are invalid. Errors are listed per row so you can see exactly what to fix.
6. When you are happy, click **Confirm import**. Only the valid rows are created; invalid rows are skipped.
7. The **Import result** appears, listing the **Imported users** and any **Skipped rows** with their reasons.

**How you can tell it worked:** the result summary shows the number imported, and each newly created person receives their own single-use password-setup link, exactly as with a single create.

**Common problems and what to do:**
- *A whole-file error (for example, the file isn't a valid CSV or the columns are wrong).* — A red message explains the problem. Fix the file and start again with **Import another file**.
- *Some rows skipped.* — Read the listed reasons (often a duplicate email, a missing required field, or a lecturer with no valid department code). Correct just those rows in your spreadsheet and re-import them.
- *After previewing, the confirm button is greyed out.* — Re-pick the file with **Choose CSV**; the file must be present again to confirm the import.

### Find, filter, and search users

The **Users** page lists every staff and admin account. To narrow it down:

- **Filter by role** — pick one or more roles.
- **Status** — show Active, Pending invite, or Deactivated accounts.
- **Search name or email** — type to find a specific person.

Each row shows the person's name and email, their employee ID (if any), their role tags, and a **status** tag:
- **Active** — they have set their password and can sign in.
- **Pending invite** — invited, but they haven't set their password yet.
- **Deactivated** — the account is switched off and cannot sign in.

### Deactivate or restore a user

Deactivating an account immediately prevents that person from signing in. It is reversible — nothing is permanently deleted.

1. On the **Users** page, click the **Deactivate** (trash) button on the person's row, or use **Deactivate** in their **Account actions** card.
2. Confirm the prompt.

To bring an account back, set the **Status** filter to show deactivated accounts, find the person, and click the **Restore** button on their row.

**Note:** you cannot deactivate your own account.

### Manage reference data

Reference data is the set of lookup lists the application form and the rest of the system rely on. Open **Reference data** from the dashboard or the menu; it offers four areas, each as a card: **Departments**, **Program offerings**, **Document types**, and **Level requirements**.

Every reference page works the same way: a table of existing entries, a **New …** button to add one, **Edit** (pencil) and **Delete** (trash) buttons per row, a **Show deleted** toggle to reveal removed entries, and a **Restore** button to bring a deleted entry back. Deleting is reversible.

#### Departments

Academic departments offered by the institution.

1. Open **Departments**, click **New department**.
2. Enter a **Name** and a **Code**, and optionally a **Description**.
3. Click **Create** (or **Save changes** when editing).

The **Offerings** column shows how many degree programmes reference each department.

#### Program offerings

The degree programmes each department offers, and the level range for each (for example, levels 1 to 3).

1. Open **Program offerings**, click **New offering**.
2. Choose a **Department** and a **Degree program**, then set **Min level** and **Max level**.
3. Click **Create**.

The **Requirements** column shows how many document requirements depend on each offering.

#### Document types

The credentials and identity documents that the application form can ask applicants to upload.

1. Open **Document types**, click **New document type**.
2. Enter a **Name** and a **Code**, and optionally a **Description**.
3. Click **Create**.

The **Used by** column shows how many level requirements reference each document type.

#### Level requirements

For a given programme and level, which documents an applicant must (or may) provide.

1. Open **Level requirements**, click **New requirement**.
2. Choose a **Program offering** — the level range allowed for that offering is shown automatically.
3. Set the **Level**, choose the **Document type**, and use the **Required** toggle to mark whether the document is mandatory at that level. Add **Notes** if helpful.
4. Click **Create**.

### Configure tuition fees

Fee schedules set the tuition total — and any installment deadlines — for a programme, level, and academic year. These deadlines are what the system uses to decide whether students are paid up.

1. Open **Fees** from the menu. The page is titled **Fee schedules**.
2. Click **New schedule**.
3. Fill in the schedule:
   - **Program offering** — which programme this applies to.
   - **Level** — the allowed range for the chosen offering is shown.
   - **Academic year** — for example, 2026.
   - **Total (XAF)** — the full tuition amount, in CFA francs.
4. Add **Installments** if students may pay in parts:
   - Click **Add installment**.
   - For each one, set the **#** (sequence/order), a **Label** (for example, "First installment"), the **Amount**, and the **Due date**.
   - The running **Installments total** is shown; it turns red if it exceeds the overall total, which is a sign to correct the amounts.
   - Leave the installments empty if the whole total is payable as a single amount.
5. Click **Create** (or **Save changes** when editing).

As with reference data, you can **Edit**, **Delete**, **Show deleted**, and **Restore** schedules — deletion is reversible.

**A note on impact:** fee schedules and their deadlines drive the payment standing that controls things like exam-hall access. Editing an active schedule changes what students owe, so make changes deliberately, especially during an exam period.

### Review the audit log

The audit log is a permanent, **read-only** record of significant actions across SchuLyf — who did what, when, and to which record. Entries cannot be edited or deleted by anyone, including you.

1. Click **Open audit log** on the dashboard (or **View audit log** on a user's Edit page to see just that person's activity).
2. Use the filters at the top to narrow the list:
   - **Actor user ID** — the account that performed the action.
   - **Action** — the type of event (for example, created, updated, deleted, role assigned, sign-in failed).
   - **Subject type** — the kind of record affected.
   - **From** / **To** — a date range.
3. Click any row's expander to reveal the full **Changes** and **Context** details for that event.
4. Use **Clear filters** to reset, or **Refresh** to reload the latest entries.

When opened from a specific user's page, the log is automatically limited to entries where that person was either the one acting or the account affected.

For more on what is recorded and why the log is immutable, see [Security](../security.md).

---

## Notifications

- **Invitation emails.** When you create a user (singly or by import) or click **Resend invite**, the system emails that person a single-use link to set their password. Resending always invalidates the previous link.
- **On-screen confirmations.** Most actions show a brief on-screen toast confirming success, and the relevant table or status updates immediately (for example, a new user appears as **Pending invite**).
- **The audit log** is your standing record of activity. It is not a live alert feed, but it is where you go to confirm exactly what happened and when.

You manage your own email notifications and account contact details through your **Settings** (below).

---

## Staff settings

Click your account in the navigation to reach **Settings**. As an administrator you have:

- **Profile** — your name and email.
- **Security** — change your password, and enable or disable **two-factor authentication** (a one-time PIN from an authenticator app on your phone, asked for at sign-in). Recovery codes are provided when 2FA is on.
- **Appearance** — switch between light and dark display.

Administrator accounts have no extra "staff profile" fields, so the staff-profile settings used by lecturers, accountants, and SAOs do not apply to you.

---

## FAQ / troubleshooting

**A new staff member never received their invitation email.**
Open their record on the **Users** page and click **Resend invite**. This sends a fresh single-use link and cancels the old one. Double-check the email address on file is correct.

**Someone's invite link expired.**
Same fix: **Resend invite** generates a new link.

**I need to remove someone's access right now.**
Use **Deactivate** on their row or in their Account actions. They can no longer sign in. You can **Restore** them later — nothing is lost.

**I accidentally deleted a department / document type / fee schedule.**
Turn on the **Show deleted** toggle on that page, find the entry, and click **Restore**.

**I can't deactivate my own account or change my own role.**
That is by design — it protects against locking yourself out. Ask another administrator to make the change.

**A CSV import skipped some rows.**
Open the **Import result** and read each skipped row's reason (commonly a duplicate email, a missing required field, or a lecturer without a valid department code). Fix only those rows and re-import.

**The "Confirm import" button is greyed out after previewing.**
Re-select the file with **Choose CSV**. The file must be present at the moment you confirm.

**Can I change a user's email for them?**
No. Email changes are handled by the user themselves through the password-reset/verification flow. You can change their name, employee ID, and role-specific details.

**Where do I see exactly what another admin changed?**
Open the **Audit log** and filter by their Actor user ID, or open their Edit page and click **View audit log**. The log is read-only and cannot be altered.

---

*Related reading:* [Admin user management module](../modules/admin-user-management.md) · [Security (sign-in, invite links, audit log)](../security.md)
