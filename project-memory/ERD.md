# Module-Wise Entity Relationship Diagram

This document maps the current Laravel migrations into logical modules. The `issue_pins` table is included alongside the earlier ERD so the diagram reflects the latest schema.

![Module-wise ERD](erd.png)

## 1. Identity and Project Access

```mermaid
erDiagram
    USERS {
        bigint id PK
        string name
        string email UK
        boolean is_admin
        string avatar_path
    }
    ROLES {
        bigint id PK
        string name
        string slug UK
    }
    PERMISSIONS {
        bigint id PK
        string name
        string slug UK
    }
    ROLE_PERMISSIONS {
        bigint id PK
        bigint role_id FK
        bigint permission_id FK
    }
    PROJECT_MEMBERS {
        bigint id PK
        bigint project_id FK
        bigint user_id FK
        bigint role_id FK
    }

    USERS ||--o{ PROJECT_MEMBERS : joins
    ROLES ||--o{ PROJECT_MEMBERS : assigns
    ROLES ||--o{ ROLE_PERMISSIONS : grants
    PERMISSIONS ||--o{ ROLE_PERMISSIONS : enables
```

## 2. Client and Project Workspace

```mermaid
erDiagram
    USERS {
        bigint id PK
        string name
        string email UK
    }
    CLIENTS {
        bigint id PK
        bigint user_id FK
        string name
        string email
    }
    PROJECTS {
        bigint id PK
        bigint client_id FK
        bigint user_id FK
        string name
        text description
    }
    PROJECT_MEMBERS {
        bigint id PK
        bigint project_id FK
        bigint user_id FK
        bigint role_id FK
    }

    USERS ||--o{ CLIENTS : owns
    USERS ||--o{ PROJECTS : creates
    CLIENTS ||--o{ PROJECTS : contains
    PROJECTS ||--o{ PROJECT_MEMBERS : has
```

## 3. Issues, Tags, and Pins

```mermaid
erDiagram
    USERS {
        bigint id PK
        string name
    }
    PROJECTS {
        bigint id PK
        bigint client_id FK
        bigint user_id FK
        string name
    }
    ISSUES {
        bigint id PK
        bigint project_id FK
        bigint user_id FK
        bigint parent_id FK
        string title
        text description
        enum status
        timestamp done_at
    }
    ISSUE_TAGS {
        bigint id PK
        bigint project_id FK
        string name
        string slug
    }
    ISSUE_ISSUE_TAG {
        bigint id PK
        bigint issue_id FK
        bigint issue_tag_id FK
    }
    ISSUE_PINS {
        bigint id PK
        bigint user_id FK
        bigint issue_id FK
    }

    PROJECTS ||--o{ ISSUES : contains
    USERS ||--o{ ISSUES : creates
    ISSUES ||--o{ ISSUES : parent_child
    PROJECTS ||--o{ ISSUE_TAGS : defines
    ISSUES ||--o{ ISSUE_ISSUE_TAG : tagged
    ISSUE_TAGS ||--o{ ISSUE_ISSUE_TAG : maps
    USERS ||--o{ ISSUE_PINS : pins
    ISSUES ||--o{ ISSUE_PINS : is_pinned
```

## 4. Issue Attachments and References

```mermaid
erDiagram
    ISSUES {
        bigint id PK
        bigint project_id FK
        string title
    }
    ISSUE_IMAGES {
        bigint id PK
        bigint issue_id FK
        string path
        string original_name
        string mime_type
        bigint size
    }
    ISSUE_FILES {
        bigint id PK
        bigint issue_id FK
        string path
        string original_name
        string mime_type
        bigint size
    }
    ISSUE_LINKS {
        bigint id PK
        bigint issue_id FK
        string url
        string label
        boolean is_external
    }

    ISSUES ||--o{ ISSUE_IMAGES : has
    ISSUES ||--o{ ISSUE_FILES : has
    ISSUES ||--o{ ISSUE_LINKS : references
```

## 5. Application Support Tables

```mermaid
erDiagram
    USERS {
        bigint id PK
        string email UK
    }
    SITE_META {
        bigint id PK
        string key UK
        text value
    }
    PASSWORD_RESET_TOKENS {
        string email PK
        string token
    }
    SESSIONS {
        string id PK
        bigint user_id
        integer last_activity
    }
    CACHE {
        string key PK
        mediumtext value
        bigint expiration
    }
    CACHE_LOCKS {
        string key PK
        string owner
        bigint expiration
    }
    JOBS {
        bigint id PK
        string queue
        tinyint attempts
    }
    JOB_BATCHES {
        string id PK
        string name
        integer total_jobs
    }
    FAILED_JOBS {
        bigint id PK
        string uuid UK
        timestamp failed_at
    }

    USERS ||--o{ SESSIONS : opens
```

## Relationship and integrity notes

- `project_members` is unique per `project_id + user_id`; a member receives a role through `role_id`.
- `role_permissions` is unique per `role_id + permission_id`.
- `issue_tags` are project-scoped and unique per `project_id + slug`.
- `issue_issue_tag` is the many-to-many issue/tag pivot and is unique per `issue_id + issue_tag_id`.
- `issue_pins` is unique per `user_id + issue_id`.
- Deleting a project cascades to its issues, tags, and memberships. Deleting an issue cascades to its attachments, links, tags, and pins; child issues retain their row with `parent_id` set to `null`.
