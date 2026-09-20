# Project Workflow & Verification Process

This document outlines the standard operational workflow for development, features, and pair programming on this project.

## Core Rules

1. **Always consult `project-memory/` first:**
   - Before researching or implementing any feature, bugfix, or architectural change, always review the reference documentation in `project-memory/` (`FEATURES.md`, `CODE_STRUCTURE.md`, `REUSABLE_BUILDING_BLOCKS.md`, `ERD.md`, etc.).
   - Adhere to the established repository conventions and architectural patterns.

2. **Feature Implementation & Verification Workflow:**
   - **Step 1: Implementation & Local Validation**
     - Build or tweak the requested feature cleanly.
     - Validate locally with automated tests (`php artisan test`) and asset compilation (`npm run build`).
   - **Step 2: User Verification Pause**
     - Present the completed feature to the user.
     - Provide clear guidance on what changed and ask the user to verify/test the behavior in the application.
     - **Do not** update progress in `project-memory/FEATURES.md` during this step.
   - **Step 3: Progress Documentation in Project Memory**
     - Once the user has verified and approved the feature, update `project-memory/FEATURES.md` (and any related docs such as `VUE_PAGES.md` or `REUSABLE_BUILDING_BLOCKS.md`).
     - Log the update with the current date, component links, and descriptive bullet points under `## Feature history (newest first)`.
