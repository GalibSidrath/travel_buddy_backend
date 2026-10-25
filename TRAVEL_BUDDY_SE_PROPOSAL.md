# Software Engineering Project Proposal: Travel Buddy
**A Secure, Collaborative Travel Companion Matching & Trip Management Platform**

---

## 1. Executive Summary & Problem Statement

### 1.1 Problem Statement
Traveling solo or organizing group tours often introduces significant friction:
- **Budget Inefficiencies:** Solo travelers absorb the total costs of accommodation, private rentals, and vehicle bookings without cost-sharing.
- **Safety & Trust Deficit:** Connecting with unknown travelers over unstructured social platforms (e.g., Facebook groups) carries safety risks due to lack of identity verification (KYC/NID).
- **Disorganized Coordination:** Trip planning involves fragmented tools—posts on social media, negotiations over WhatsApp/Messenger, and unorganized spreadsheets—leading to missed updates, unconfirmed headcounts, and seat overbooking.

### 1.2 Proposed Solution: "Travel Buddy"
**Travel Buddy** is a centralized, role-based collaborative travel platform that bridges the gap between travel organizers ("Hosts") and adventure seekers ("Travelers"). The platform provides:
- Verified traveler profiles through government NID picture verification.
- Structured trip creation with seat tracking, categorized budgets, and itineraries.
- Formal trip request, host review, and confirmation workflows.
- Threaded trip discussion boards and secure 1-on-1 private messaging.
- Role-based JWT security and automated OTP email verification.

---

## 2. Software Development Life Cycle (SDLC) Model

### Methodology: Agile Scrum
For the Software Engineering lifecycle of Travel Buddy, the **Agile Scrum** methodology is adopted.

```mermaid
graph LR
    A[Product Backlog] --> B[Sprint Planning]
    B --> C[Sprint 1: Auth & User KYC]
    C --> D[Sprint 2: Trip Engine & Bookmarks]
    D --> E[Sprint 3: Requests & Messaging]
    E --> F[Sprint Review & QA]
    F --> G[Production Increment]
    F -.-> B
```

**Justification:**
1. **Incremental Delivery:** Core modules (Authentication, Trip Listing) were built and tested first, followed by complex interactive modules (Trip Requests, Seat Calculation, Threaded Comments, Real-time Chat).
2. **Postman API Integration Testing:** Each sprint concludes with automated regression testing using Postman API test collections.
3. **Flexibility:** Allows continuous refinement of user experience based on feedback before final deployment.

---

## 3. System Requirements Specification (SRS)

### 3.1 Functional Requirements (FR)
- **FR-01: Identity Verification & Authentication**
  - Users must register with username, email, full name, profile photo, and NID document image.
  - Passwords must be hashed using `bcrypt` (cost factor 10).
  - JWT tokens must authenticate all protected API endpoints.
  - Password recovery must enforce time-expiring 6-digit OTP verification via SMTP mailer.
- **FR-02: Trip Lifecycle Management**
  - Verified hosts can publish trips specifying destination, budget, date range, transport method, cover photo, category, and total seats.
  - Hosts can update or cancel their hosted trips.
  - Travelers can browse, filter by category (e.g., Adventure, Relaxation), and search trips.
- **FR-03: Trip Join Request & Seat Allocation Engine**
  - Registered travelers can submit join requests (`pending` status).
  - Hosts can accept or reject requests.
  - Accepting a request decrements `available_seats` and commits the traveler to `confirm_trip`.
  - Seat overbooking must be prevented at the database/transaction level.
- **FR-04: Threaded Trip Community Discussions**
  - Users can post public questions on trip details.
  - Hierarchical parent-child replies (`parent_id`) must allow organized Q&A threads.
- **FR-05: Integrated Private Messaging System**
  - Auto-generated chat rooms between host and accepted/interested travelers.
  - Message exchange with read receipts (`seen_status`).
- **FR-06: Bookmark & Watchlist Management**
  - Users can save prospective trips to their personal watchlist for rapid tracking.

### 3.2 Non-Functional Requirements (NFR)
- **NFR-01: Security & Confidentiality:** Passwords stored only as one-way cryptographic hashes. Sensitive NID documents protected on server storage with restricted access permissions.
- **NFR-02: Performance & Response Time:** REST API endpoints respond in < 200 ms under standard loads. Database queries optimized with foreign keys and compound unique indexes.
- **NFR-03: Data Integrity & ACID Compliance:** Referential integrity enforced via MySQL foreign keys with cascading deletes (`ON DELETE CASCADE`) to prevent orphaned comments, bookmarks, and chat records.
- **NFR-04: Usability & Responsiveness:** Cross-platform responsiveness across mobile, tablet, and desktop viewports.
- **NFR-05: Maintainability:** Modular API routing architecture with distinct separation of concerns (`routes/auth`, `routes/trips`, `routes/chat`, `routes/comments`, `routes/bookmarks`).

---

## 4. Software Engineering Design Diagrams

### 4.1 System Architecture Diagram (3-Tier Architectural Pattern)

```mermaid
graph TD
    subgraph Presentation Layer [Client / Frontend Layer]
        UI[Single Page App / Mobile Web Interface]
        ClientStorage[Local Storage: JWT Token Session]
    end

    subgraph Application / API Layer [Backend Business Logic]
        Router[API Central Router - index.php]
        AuthMid[JWT Auth Middleware & Validator]
        Mailer[PHPMailer SMTP Engine - OTP Service]
        
        subgraph Controller Modules
            AuthCtrl[Auth & Profile Controller]
            TripCtrl[Trip & Seat Engine]
            ChatCtrl[Chat & Messaging Engine]
            CommentCtrl[Threaded Comment Service]
            BookmarkCtrl[Bookmark Service]
        end
    end

    subgraph Data Persistence Layer [Database Storage]
        DB[(MySQL Database: travel_buddy)]
        FileStore[(Local Uploads: Profiles, NID, Trip Covers)]
    end

    UI -->|HTTP REST / JSON / Multipart| Router
    Router --> AuthMid
    AuthMid --> AuthCtrl
    AuthMid --> TripCtrl
    AuthMid --> ChatCtrl
    AuthMid --> CommentCtrl
    AuthMid --> BookmarkCtrl

    AuthCtrl --> Mailer
    AuthCtrl --> DB
    AuthCtrl --> FileStore
    TripCtrl --> DB
    TripCtrl --> FileStore
    ChatCtrl --> DB
    CommentCtrl --> DB
    BookmarkCtrl --> DB
```

---

### 4.2 Data Flow Diagrams (DFD)

#### DFD Level 0 (Context Diagram)
```mermaid
flowchart TD
    Traveler[Traveler / User]
    Host[Trip Host / Organizer]
    SMTP[External SMTP Mail Service]
    System((Travel Buddy System))

    Traveler -->|Registration, Login, NID Upload| System
    Traveler -->|Search Trips, Submit Join Request, Post Comments| System
    Traveler -->|Send Chat Messages, Save Bookmarks| System
    System -->|JWT Token, Trip Feeds, Chat History, Notifications| Traveler

    Host -->|Create Trip, Update Itinerary, Upload Cover| System
    Host -->|Accept / Reject Join Requests, Reply to Chats| System
    System -->|Applicant Profiles, Request Alerts, Confirmed Passenger List| Host

    System -->|Dispatch 6-Digit OTP Email| SMTP
    SMTP -->|Deliver Verification Code| Traveler
```

---

#### DFD Level 1 (Decomposition Diagram)
```mermaid
flowchart TD
    User([User / Traveler / Host])

    P1[1.0 Authentication & KYC Verification]
    P2[2.0 Trip Catalog & Seat Engine]
    P3[3.0 Booking & Request Management]
    P4[4.0 Communication & Threaded Comments]
    P5[5.0 Bookmark & Watchlist Management]

    D1[(D1: user_info / user_login)]
    D2[(D2: trip_info)]
    D3[(D3: trip_request / confirm_trip)]
    D4[(D4: chat_room / message)]
    D5[(D5: trip_comment)]
    D6[(D6: bookmark)]
    D7[(D7: password_reset)]

    User -->|Credentials, NID pic| P1
    P1 -->|Store User & Hash| D1
    P1 -->|Generate/Verify OTP| D7
    P1 -->|Issue JWT Token| User

    User -->|Trip Details, Budget, Dates| P2
    P2 -->|Create/Fetch Trips| D2
    D2 -->|Trip Catalog Feed| User

    User -->|Submit Join Request| P3
    P3 -->|Store Pending Request| D3
    D3 -->|Host Reviews & Accepts| P3
    P3 -->|Decrement Available Seats| D2
    P3 -->|Add to Confirmed Roster| D3

    User -->|Post Question / Reply| P4
    P4 -->|Store Nested Comment| D5
    User -->|Send 1-on-1 Message| P4
    P4 -->|Store Room & Message| D4

    User -->|Toggle Bookmark| P5
    P5 -->|Update Bookmark Records| D6
```

---

### 4.3 Use Case Diagram (UML)

```mermaid
flowchart LR
    Guest((Unregistered Guest))
    Traveler((Registered Traveler))
    Host((Trip Host))

    UC1([Register & Upload NID])
    UC2([Login & Authenticate])
    UC3([Forgot Password via OTP])
    UC4([Browse & Search Trips])
    UC5([Create New Trip])
    UC6([Manage Hosted Trips])
    UC7([Send Trip Join Request])
    UC8([Accept/Reject Join Requests])
    UC9([Bookmark Trip])
    UC10([Post Comment / Reply])
    UC11([1-on-1 Chat with Host])

    Guest --> UC1
    Guest --> UC2
    Guest --> UC3
    Guest --> UC4

    Traveler --> UC2
    Traveler --> UC4
    Traveler --> UC7
    Traveler --> UC9
    Traveler --> UC10
    Traveler --> UC11

    Host --> UC2
    Host --> UC5
    Host --> UC6
    Host --> UC8
    Host --> UC10
    Host --> UC11

    UC7 -.->|<<include>>| UC2
    UC5 -.->|<<include>>| UC2
    UC8 -.->|<<include>>| UC2
    UC8 -.->|<<updates seats in>>| UC6
```

---

### 4.4 Entity-Relationship Diagram (ERD / Relational Schema)

```mermaid
erDiagram
    user_info ||--|| user_login : "authenticates via"
    user_info ||--o{ trip_info : "hosts"
    user_info ||--o{ trip_request : "requests"
    user_info ||--o{ confirm_trip : "confirmed in"
    user_info ||--o{ bookmark : "saves"
    user_info ||--o{ trip_comment : "writes"
    user_info ||--o{ chat_room : "participates in"
    user_info ||--o{ message : "sends"

    trip_info ||--o{ trip_request : "receives"
    trip_info ||--o{ confirm_trip : "contains"
    trip_info ||--o{ bookmark : "bookmarked in"
    trip_info ||--o{ trip_comment : "has discussions"
    trip_info ||--o{ chat_room : "linked to"

    chat_room ||--o{ message : "contains"
    trip_comment ||--o{ trip_comment : "replies to (parent_id)"

    user_info {
        varchar username PK
        varchar full_name
        varchar email UK
        enum gender
        varchar country
        date dob
        varchar profile_pic
        varchar nid_pic
    }

    user_login {
        varchar username PK, FK
        varchar password "Bcrypt Hash"
    }

    password_reset {
        varchar email PK
        varchar otp "6-digit OTP"
        datetime expires_at
    }

    trip_info {
        int id PK
        varchar title
        varchar host_username FK
        varchar destination
        date start_day
        date end_day
        decimal budget
        varchar category
        varchar transport_method
        text description
        int total_seats
        int available_seats
        varchar cover_picture
    }

    trip_request {
        int id PK
        int trip_id FK
        varchar username FK
        enum status "pending, accepted, rejected"
        timestamp created_at
    }

    confirm_trip {
        int id PK
        int trip_id FK
        varchar username FK
    }

    trip_comment {
        int id PK
        int trip_id FK
        varchar username FK
        int parent_id FK "Recursive Self Key"
        text comment_text
        timestamp created_at
    }

    bookmark {
        int id PK
        varchar username FK
        int trip_id FK
        timestamp created_at
    }

    chat_room {
        int id PK
        int trip_id FK
        varchar username FK
        varchar host_username FK
        timestamp created_at
    }

    message {
        int id PK
        int room_id FK
        varchar sender_username FK
        text message_text
        tinyint seen_status
        timestamp created_at
    }
```

---

### 4.5 Sequence Diagram: Trip Join Request & Acceptance Workflow

```mermaid
sequenceDiagram
    autonumber
    actor Traveler as Traveler (Client)
    actor Host as Trip Host (Client)
    participant API as Travel Buddy API Server
    participant DB as MySQL Database

    Traveler->>API: POST /trips/join (trip_id, JWT Bearer Token)
    API->>DB: Validate Token & Check Available Seats (available_seats > 0)
    alt No Seats Available
        API-->>Traveler: 400 Bad Request ("Trip is fully booked!")
    else Seats Available
        API->>DB: INSERT INTO trip_request (trip_id, username, status='pending')
        DB-->>API: Request Recorded (ID: 5)
        API-->>Traveler: 201 Created ("Join request submitted successfully!")
    end

    Note over Host, API: Host views incoming pending applications
    Host->>API: GET /trip_requests?trip_id=4 (JWT Bearer Token)
    API->>DB: SELECT * FROM trip_request WHERE trip_id=4 AND status='pending'
    DB-->>API: Return pending applicants list (with NID verification status)
    API-->>Host: 200 OK [Applicants Data]

    Host->>API: POST /trips/accept (request_id, trip_id, applicant_username)
    API->>DB: BEGIN TRANSACTION
    API->>DB: UPDATE trip_request SET status='accepted' WHERE id=request_id
    API->>DB: INSERT INTO confirm_trip (trip_id, username)
    API->>DB: UPDATE trip_info SET available_seats = available_seats - 1 WHERE id=trip_id
    API->>DB: INSERT INTO chat_room (trip_id, username, host_username) (Auto-initiate Chat)
    API->>DB: COMMIT TRANSACTION
    API-->>Host: 200 OK ("Traveler accepted, seat booked, chat room initialized!")
```

---

## 5. Software Quality Assurance (SQA) & Testing Strategy

### 5.1 Automated API Testing (Postman Test Suite)
The Travel Buddy backend includes a comprehensive, automated Postman Collection (`travel_buddy_postman_collection.json`) evaluating:
1. **Status Code Assertions:** `pm.response.to.have.status(200 / 201)`
2. **Environment Token Persistence:** Automatic parsing of issued JWT tokens from `/auth/login` and `/auth/registration` into collection runtime variables.
3. **Multipart Form-Data Validation:** Verification of file uploads (`profile_pic`, `nid_pic`, `cover_picture`) alongside JSON payloads.
4. **Boundary Condition Testing:** Attempting to join a trip with `available_seats = 0` or submitting invalid OTPs.

### 5.2 Security & Vulnerability Analysis
- **SQL Injection Prevention:** 100% of queries use PDO Prepared Statements with bound parameters (`$stmt->prepare()`, `$stmt->execute()`).
- **Authentication Hijacking Defense:** Stateless JWT signatures using secret key verification and explicit expiration timestamps.
- **Cross-Site Scripting (XSS) Sanitization:** Stripping tags and escaping inputs prior to storing comments and chat messages.

---

## 6. Risk Assessment & Mitigation Plan

| Risk ID | Identified Risk | Impact | Probability | Mitigation Strategy |
| :--- | :--- | :--- | :--- | :--- |
| **R-01** | Fraudulent / Fake Traveler Profiles | High | Medium | Mandatory National ID (NID) image upload during registration; Host review before acceptance. |
| **R-02** | Race Conditions in Seat Booking | High | Low | Atomic SQL transactions and check-before-decrement logic when approving join requests. |
| **R-03** | Unauthorized API Access | High | Medium | JWT middleware interceptor checking bearer tokens on all mutating endpoints. |
| **R-04** | Spam in Public Comment Threads | Medium | Medium | Foreign key enforcement and recursive parent-child moderation by trip hosts. |

---

## 7. Future Software Enhancements (Academic Roadmap)
1. **Escrow Payment Gateway Integration:** Integration of bKash/Nagad/SSLCommerz API to hold booking deposits until trip completion.
2. **Real-time WebSockets Engine:** Migrating HTTP polling chat into real-time WebSockets (Socket.io / Ratchet PHP) for instant message delivery.
3. **AI Companion Recommendation:** Machine Learning clustering based on travel budget, preferred destinations, and past traveler ratings.
4. **Trust Score & Review System:** Mutual 5-star ratings and verified traveler badges awarded upon completion of confirmed trips.
