You are an expert software architect and security analyst. Your task is to fully scan, read, analyze, and reverse-engineer the entire codebase with a primary focus on a **Permission-Based Access Control (PBAC)** system built on:

* **Access Levels = Menus (parent access)**
* **Permissions = Submenus / child actions (granular access)**

Your goal is not just to describe the system, but to **deeply understand how it works end-to-end and produce a reusable blueprint** that can be implemented in another project.

---

## 🧠 Core Understanding Goals

### 1. Access Level (Menu) System

* Identify how **menus (access levels)** are defined and stored
* Determine how the system checks:

  * if a user has access to a menu
* Trace where and how this check is enforced:

  * backend (middleware, guards, controllers)
  * frontend (menu rendering, route protection)

---

### 2. Permission (Submenu / Child) System

* Identify how **permissions (submenus or actions)** are structured
* Determine how permissions relate to access levels:

  * Is it hierarchical, mapped, or independent?
* Trace how the system checks:

  * if a user has permission for a submenu/action under a menu

---

### 3. Full Authorization Flow (CRITICAL)

You must trace the **complete execution flow**:

* How user identity is captured (login/session/token)
* How access levels and permissions are loaded
* How they are stored (state, cache, context, JWT, etc.)
* How checks are triggered when:

  * opening a menu
  * clicking a submenu
  * calling an API
* What happens when access is denied

Provide **step-by-step flow tracing**, not summaries.

---

### 4. Menu & Submenu Rendering Logic

* Analyze how menus are:

  * generated
  * filtered
  * displayed
* Identify:

  * how hidden menus/submenus are handled
  * how unauthorized items are removed or disabled
* Explain how frontend and backend stay consistent

---

### 5. Database & Data Relationships

* Identify all structures related to:

  * users
  * access levels (menus)
  * permissions (submenus/actions)
* Explain:

  * relationships (one-to-many, many-to-many, mapping tables)
  * how permissions are queried and joined
* Trace how data flows from DB → backend → frontend

---

### 6. System Mechanics (Deep Analysis)

Explain how the system:

* captures user access data
* generates permission states
* validates access at runtime
* handles edge cases such as:

  * user has menu but no submenu
  * user accesses API directly without UI
  * mismatched or missing permissions

---

### 7. Recreation Blueprint (IMPORTANT)

Reverse-engineer the system into a **clean, reusable design**:

* Define:

  * required tables / schemas
  * core services (auth, permission checker, menu builder)
  * validation flow
* Provide a **step-by-step guide** to rebuild this PBAC system
* Keep it **framework-agnostic**

---

### 8. Portability & Abstraction

* Abstract the system so it can be reused in another project
* Identify:

  * framework-specific logic and how to replace it
* Suggest a modular design:

  * permission engine
  * menu builder
  * access validator

---

### 9. Security Review

* Identify weaknesses such as:

  * missing backend validation (frontend-only checks)
  * bypass risks
  * inconsistent permission enforcement
* Suggest improvements:

  * stricter validation points
  * better structure for scalability 

---

## 📌 Output Requirements

Produce a structured report with:

* High-level architecture overview
* Access Level (Menu) system breakdown
* Permission (Submenu) system breakdown
* Full execution flow (step-by-step)
* Menu rendering logic
* Database schema explanation
* Reusable PBAC architecture blueprint
* Security issues and recommendations

---

## ⚠️ Strict Rules

* Do NOT assume, trace everything from actual code
* Do NOT generalize into RBAC or ABAC unless explicitly detected
* Focus on **PBAC (menu → submenu relationship)**
* Prioritize **actual implementation behavior over theory**

I want it as pbac-instruction.md
