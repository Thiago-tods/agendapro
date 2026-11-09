# AgendaPro — Project Instructions

## 1. Project identity

AgendaPro is a multi-tenant appointment scheduling and business management SaaS for service-based businesses, including clinics, barbershops, consultancies, studios and workshops.

The application must provide reliable appointment management, customer management, professional management, service catalogs, availability management and business insights.

The product must feel like a real commercial SaaS, not a generic dashboard template or an AI-generated admin interface.

Primary language: Portuguese (Brazil).
Default locale: pt-BR.
Default currency: BRL.
Default timezone: America/Sao_Paulo.

## 2. Technology stack

### Backend
- PHP 8.4
- Laravel 13
- PostgreSQL 18
- Laravel Sanctum
- REST API under /api/v1
- PHPUnit or Pest for automated tests

### Frontend
- Vue 3
- TypeScript
- Vite
- Vue Router
- Pinia
- Tailwind CSS
- Vitest
- Playwright

### Engineering architecture
Use a modular monolith.

The frontend and backend are separate applications:
- backend/: Laravel API
- frontend/: Vue SPA

Do not introduce microservices, distributed infrastructure, message queues or caching systems without a concrete business or technical justification.

Do not introduce unnecessary abstractions or repository layers when Eloquent and application services are sufficient.

## 3. Architecture principles

Organize the backend by business responsibility.

Expected areas:
- Authentication
- Companies
- Memberships and roles
- Customers
- Professionals
- Services
- Availability
- Appointments
- Audit and history

Keep responsibilities separated between:
- HTTP controllers
- Form Requests and validation
- Application services/use cases
- Domain rules
- Eloquent models
- Policies and authorization
- API Resources

All endpoints must be versioned under /api/v1.

Use UUIDs for primary identifiers.

Use PostgreSQL as the source of truth.

### Multi-tenancy

AgendaPro is a multi-tenant application.

Every company-owned resource must be isolated by company.

Never trust a company_id supplied by the frontend without checking the authenticated user's membership and permissions.

Apply tenant restrictions in backend queries, policies and application services.

Never rely exclusively on frontend restrictions for security.

### Authentication

Use Laravel Sanctum with an appropriate SPA cookie-based authentication flow.

Configure CSRF protection, session security, CORS and authorization correctly.

### Appointment business rules

- Prevent overlapping active appointments for the same professional.
- Use half-open time intervals: [start, end).
- Consider service duration and buffer time.
- Respect company operating hours.
- Respect professional working hours.
- Respect availability exceptions and blocked periods.
- Validate existing appointments before accepting a booking.
- Use database transactions for critical appointment operations.
- Implement PostgreSQL-level protection against concurrent conflicting bookings where appropriate.
- Store timestamps in UTC.
- Display dates and times using the company's configured timezone.
- Preserve service name, price, duration and buffer snapshots on each appointment.
- Do not hard-delete appointments.
- Record cancellation, rescheduling and status transitions in appointment history.

## 4. Official visual identity

The official visual direction is:

Friendly SaaS + Professional Dark Interface.

The visual language must be:
- Friendly without looking childish.
- Modern without looking generic.
- Spacious without wasting screen space.
- Colorful in controlled details.
- Professional enough for business operations.

Do not independently redesign the application during implementation.

### Design decisions

- Style: Friendly SaaS.
- Theme: Dark only for the initial release.
- Density: Spacious.
- Accent: Purple.
- Border radius: Small.
- Dashboard: Hybrid operational and managerial.
- Main agenda: Traditional calendar grid.
- Typography: IBM Plex Sans.
- Technical typography: IBM Plex Mono.

## 5. Design tokens

Use the following CSS variables as the official visual foundation.

### Colors

```css
:root {
    color-scheme: dark;

    --color-background: #12111A;
    --color-surface: #1B1925;
    --color-card: #211E2D;
    --color-elevated: #292538;
    --color-border: #353044;

    --color-primary: #8B5CF6;
    --color-primary-hover: #A78BFA;
    --color-primary-soft: #34254D;
    --color-primary-text: #FFFFFF;

    --color-text-primary: #F5F3FA;
    --color-text-secondary: #B5AEC5;
    --color-text-muted: #817A91;

    --color-success: #6EE7B7;
    --color-warning: #FCD34D;
    --color-danger: #FDA4AF;
    --color-info: #7DD3FC;
}
```

### Typography

Primary font: IBM Plex Sans.

Technical and tabular font: IBM Plex Mono.

Use:
- IBM Plex Sans Regular for normal text.
- IBM Plex Sans Medium for labels and controls.
- IBM Plex Sans Semibold for headings.
- IBM Plex Mono for technical identifiers or tabular information when useful.

Typography scale:
- Page title: 28px.
- Section title: 20px.
- Card title: 16px.
- Body: 14px.
- Labels: 13px.
- Supporting text: 12px.

Avoid oversized headings and excessive font-weight variation.

Load fonts through a reliable font source and provide local/system fallbacks.

### Spacing

Use a 4px base spacing system.

- 4px
- 8px
- 12px
- 16px
- 24px
- 32px
- 40px
- 48px

Prefer generous spacing between major sections.

Do not interpret spacious as unnecessarily large cards or excessive empty space.

### Borders and radii

- Small controls: 4px.
- Inputs and buttons: 5px.
- Cards: 6px.
- Modals and dialogs: 8px.

Use subtle borders rather than strong shadows.

### Layout dimensions

- Sidebar: approximately 248px.
- Header: approximately 68px.
- Main content should adapt to available viewport width.
- Support sidebar collapse and responsive layouts.

## 6. Visual design rules

Avoid generic AI-generated dashboard aesthetics.

Do not:
- Use purple gradients everywhere.
- Put oversized decorative icons inside every card.
- Repeat identical metric cards merely to fill space.
- Add unnecessary donut charts.
- Use excessive shadows or glassmorphism.
- Use excessive rounded corners.
- Add decorative elements without a functional purpose.
- Use random colors for appointments.
- Add animations that do not improve usability.

Prefer:
- Purposeful information hierarchy.
- Distinct card compositions according to their function.
- Compact operational information.
- Clear typography.
- Subtle borders.
- Meaningful status colors.
- Consistent alignment.
- Functional whitespace.
- Realistic data tables and calendar layouts.

Do not imitate another SaaS product directly.

Use scheduling products as functional inspiration, not as templates to copy.

## 7. Dashboard direction

The dashboard must be hybrid.

It must balance operational information and business indicators.

Priorities:

1. Welcome header and primary booking action.
2. Operational indicators:
   - Today's appointments.
   - Pending appointments.
   - Completed appointments.
3. Managerial indicators:
   - Revenue.
   - Attendance rate.
   - Appointment trends.
4. Upcoming appointments.
5. Recent activity.
6. Secondary performance information.

Do not let charts dominate the dashboard.

Use different visual compositions for different types of indicators.

All metrics must be based on actual backend data once integrated.

Never leave hardcoded demonstration data in production-facing functionality.

## 8. Agenda direction

The agenda is the primary operational feature of AgendaPro.

Use a traditional calendar grid.

Required views:
- Daily.
- Weekly.
- Monthly.

Required functionality:
- Navigate between dates.
- Filter by professional.
- Filter by service.
- Display appointment duration proportionally.
- Identify unavailable periods.
- Display appointment status.
- Open appointment details.
- Create, reschedule and cancel appointments.
- Support appropriate loading and empty states.

Appointment blocks must communicate:
- Customer.
- Service.
- Professional when space allows.
- Start and end times.
- Appointment status.

Use consistent category or professional colors.

Status must also be communicated with text or an accessible visual indicator. Never rely exclusively on color.

The calendar must remain readable with many simultaneous appointments.

## 9. Other screen directions

### Customers

Provide:
- Searchable customer list.
- Filters.
- Contact information.
- Visit history.
- Last appointment.
- Customer profile.
- Archive functionality.

### Professionals

Provide:
- Professional list.
- Profile information.
- Services performed.
- Working hours.
- Availability.
- Active/inactive status.

### Services

Provide:
- Service catalog.
- Description.
- Price.
- Duration.
- Buffer time.
- Category.
- Color identifier.
- Associated professionals.
- Active/inactive status.

### Settings

Organize settings into clear sections:
- Company information.
- Business hours.
- Team and permissions.
- Profile.
- Security.
- Preferences.

## 10. Component architecture

Build reusable components with consistent behavior.

Suggested shared components:

- AppButton
- AppInput
- AppSelect
- AppBadge
- AppModal
- AppDrawer
- AppToast
- AppCard
- AppDataTable
- AppSkeleton
- AppEmptyState
- AppConfirmationDialog
- AppPagination
- AppSearchInput
- AppDatePicker

Layout components:

- AppSidebar
- AppHeader
- AppLayout
- MobileNavigation

Calendar components:

- CalendarToolbar
- CalendarGrid
- CalendarDayColumn
- AppointmentEvent
- CalendarFilters

Do not create components that are only used once unless there is a clear reason.

Keep business-specific components inside their corresponding modules.

## 11. Frontend structure

Use a modular frontend structure.

```text
src/
├── assets/
│   ├── fonts/
│   └── styles/
├── components/
│   ├── ui/
│   ├── layout/
│   └── calendar/
├── composables/
├── modules/
│   ├── dashboard/
│   ├── appointments/
│   ├── customers/
│   ├── professionals/
│   └── services/
├── router/
├── services/
├── stores/
├── types/
└── views/
```

Each module should own its specific components, types, API services and composables when appropriate.

## 12. Responsive design

Desktop is the primary administrative experience.

However, all major screens must support:
- Desktop.
- Notebook.
- Tablet.
- Mobile.

On mobile:
- Use a compact navigation pattern.
- Prefer daily agenda view.
- Adapt wide tables into cards or appropriate responsive lists.
- Use full-screen forms or drawers when needed.
- Avoid horizontal overflow.
- Keep primary actions accessible.

## 13. Accessibility and interaction

Aim for WCAG 2.2 AA.

Every interactive component must support:
- Normal.
- Hover.
- Focus.
- Active.
- Disabled.
- Loading.
- Error.
- Success.

Additional requirements:
- Keyboard navigation.
- Visible focus indicators.
- Accessible labels.
- Sufficient contrast.
- Reduced-motion preference.
- Clear validation messages.
- Feedback after operations.
- Confirmation before destructive actions.

Use subtle transitions around 150–200ms when appropriate.

## 14. Development workflow

Before implementing a feature:

1. Inspect the existing project.
2. Understand the relevant module.
3. Identify business rules.
4. Define acceptance criteria.
5. Plan affected files.
6. Implement the smallest complete solution.
7. Add or update automated tests.
8. Run relevant tests and static checks.
9. Explain what changed and why.

Never claim a feature is complete without checking its implementation.

Never claim tests passed without actually running them.

Do not silently change architectural decisions.

If an important technical decision conflicts with these instructions, explain the conflict and request approval before changing direction.

## 15. Code quality

- Use strict TypeScript.
- Avoid `any` unless technically justified.
- Keep components focused.
- Keep business rules out of presentation components.
- Validate data on the backend.
- Use explicit API contracts.
- Handle errors consistently.
- Avoid duplicated business logic.
- Write meaningful automated tests.
- Keep documentation updated.

Use single quotes in languages where appropriate and compatible with the established formatter.

## 16. Delivery expectations

For each completed feature, report:

- Files created.
- Files modified.
- Business rules implemented.
- Tests added.
- Commands executed.
- Test results.
- Known limitations.
- Recommended next step.

The goal is a reliable, maintainable commercial SaaS.

Do not optimize for the number of generated files or the speed of code production.

Optimize for correctness, maintainability, usability and professional quality.