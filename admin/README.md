# Admin control plane

The project is PHP native and does not have a React build pipeline. This
control plane therefore uses server-rendered PHP with a small set of local UI
primitives in `styles.css` that follow shadcn/ui tokens and interaction
patterns: button, card, table, badge, dialog, input, and focus states.

Keeping the primitives local avoids adding a second runtime to the API server.
If a separate React frontend is introduced later, the same API contract and
design tokens can be moved into real shadcn/ui components without changing the
admin endpoints.

The sticky navbar exposes Registry, Audit, Recap, RBAC, and documentation pages for
the Global API, Simontok API, and Monbis API. Every page remains behind the
admin session.

The RBAC page uses `?page=rbac`. Internal employees can be selected through the
server-side employee search by name or `id_peg`; external users keep the manual
form. Registered application codes are detected in the registry form and are
also protected by the database unique constraint.
