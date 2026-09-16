# Demo and public content readiness

This checklist defines the content needed for a credible public portfolio demo.
It deliberately does not invent contact details, legal identities, data-retention
periods, vendors, or operational practices that have not been decided.

## Public project presentation

The public `/about` page presents the project in product terms rather than as a
feature inventory. It emphasizes:

- useful discovery before account creation;
- backend-enforced alcohol, visibility, and moderation boundaries;
- complete recipe authoring and community workflows;
- SSR, failure states, accessibility, responsive behavior, and automated checks;
- the Nuxt, Vue, Symfony, API Platform, PostgreSQL, and Docker Compose stack.

Keep this page factual. Add personal authorship, employment availability, source
code links, or a résumé link only after the owner supplies the intended public
identity and URLs.

## Demo content to prepare

The development seed currently proves anonymous access with one published
zero-proof recipe (`Seed Lime Soda`). Before recording screenshots or sharing a
hosted recruiter demo, prepare a small editorial set with:

- at least six complete, published, alcohol-free recipes visible while signed
  out, spanning more than one category and ingredient family;
- concise original titles, descriptions, measured ingredients, ordered steps,
  preparation time, servings, and difficulty for every recipe;
- consistent landscape source photography that remains useful in the existing
  card and detail crops, with documented ownership or reuse rights;
- two or three complete public author profiles using fictional demo identities,
  not real personal data;
- a few constructive comments and favorites that demonstrate community states
  without filler or promotional language;
- one published alcoholic recipe to verify anonymous/minor denial and adult
  access, without exposing it in anonymous screenshots or sitemap assertions;
- one private draft and one archived recipe to demonstrate author workflows;
- one safely worded moderation report for the administration walkthrough.

Verify the final dataset in a signed-out browser first. The home page, recipe
index, category shelves, recipe details, profile pages, protected recipe image
delivery, and `/sitemap.xml` must expose only content the anonymous API permits.

## Contact page: information still required

Do not publish a contact page until the owner chooses and supplies:

- the public name or organization name to display;
- a public contact channel, such as a dedicated email address or external form;
- any portfolio, source repository, professional profile, or résumé URLs;
- the expected purpose of contact and, if desired, response expectations;
- anti-spam handling and the destination/retention behavior of any contact form.

A static mail link and a submitted contact form have different privacy and
security implications. Choose the mechanism before writing copy or collecting
messages.

## Privacy page: decisions and facts still required

The repository documents technical behavior, but that is not enough to state a
real privacy policy. Obtain or decide the following before publishing one:

- the data controller's legal identity and contact details;
- deployment jurisdiction, hosting provider, and any subprocessors;
- the purposes and legal bases for account, profile, moderation, abuse-control,
  security-log, and backup processing;
- retention periods and deletion procedures for accounts, uploads, comments,
  reports, refresh sessions, logs, backups, and abuse counters;
- whether analytics, telemetry, email delivery, error tracking, or other
  third-party services are enabled in the deployed environment;
- cookie details beyond the documented authentication cookie, including any
  consent requirement introduced by future services;
- who receives data, whether international transfers occur, and the safeguards;
- the process and verified contact channel for access, correction, deletion,
  objection, restriction, portability, and complaints;
- age-related account rules beyond the existing recipe-visibility rule.

Known implementation facts may be used only after confirming that production
matches them: the refresh token is an HttpOnly, SameSite=Strict cookie; the
access token is kept in memory; profiles can expose username, bio, avatar, and
join date; users can publish recipes and comments; moderation reports exist;
and local uploads are planned for the single-VPS deployment. These facts do not
establish legal bases, retention periods, or real-world operators on their own.
