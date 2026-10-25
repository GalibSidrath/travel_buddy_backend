# Project Proposal Presentation: Travel Buddy
**Collaborative Travel & Companion Matching Platform**
*Course: Software Engineering | Academic Project Proposal*

---

## Slide 1: Title Slide
- **Title:** Travel Buddy
- **Subtitle:** An Engineering Approach to Collaborative Travel Planning & Verified Companion Matching
- **Presented by:** Abdullah Mohd. Galib & Team
- **Course:** Software Engineering (CSE / SE)
- **Target Audience:** Course Instructor & Academic Review Panel

> 🗣️ **Speaker Notes (What to say):**
> *"Respected Sir and fellow classmates, good morning/afternoon. Today, we are presenting our Software Engineering project proposal titled 'Travel Buddy'—a secure, role-based collaborative platform engineered to solve the real-world friction of solo travel, cost-sharing, and stranger trust."*

---

## Slide 2: Problem Statement & Motivation
- **The Solo Travel Dilemma:** Solo travelers face high individual costs for lodging, private rentals, and transportation.
- **Trust & Security Gap:** Coordinating trips over unstructured social channels (e.g., Facebook groups) carries security risks due to lack of verified identity.
- **Fragmented Communication:** Lack of centralized seat management leads to confusion, manual tracking, and accidental overbooking.
- **Our Goal:** Engineer an integrated, reliable, and auditable software platform where travelers connect with verified companions and transparently organize trips.

> 🗣️ **Speaker Notes:**
> *"Whenever someone plans an adventure or tour, they face two major barriers: expenses and safety. While people try to find travel companions on social media, there is zero verification, leading to security concerns. Additionally, hosts struggle to track seats, payments, and applicant requests manually. Travel Buddy eliminates these pain points through systematic software design."*

---

## Slide 3: Proposed Solution & Key Capabilities
- **Verified KYC Profiles:** Registration requires government National ID (NID) image submission for verified user badges.
- **Interactive Trip Management:** Hosts publish trips with detailed itineraries, budget breakdowns, transport types, and strict seat quotas.
- **Seat Booking & Approval Engine:** Structured applicant workflow (`Pending` -> `Accepted` / `Rejected`) with atomic seat deductions.
- **Integrated Communications:**
  - Public threaded discussion boards for general Q&A.
  - Automatic private 1-on-1 chat rooms generated upon host approval.
- **Stateless JWT Security:** Secure session handling and OTP-based email password recovery via SMTP.

> 🗣️ **Speaker Notes:**
> *"Travel Buddy bridges the gap between trip organizers and aspiring travelers. It is not just a listing website; it incorporates a full lifecycle—from identity verification and seat reservation algorithms to nested comment boards and direct private messaging."*

---

## Slide 4: Software Process Model (SDLC)
- **Methodology:** Agile Scrum Framework
- **Sprint Iteration Cycle:** 2-week sprints
  - **Sprint 1:** SRS Analysis, DB Schema Design, JWT Authentication & KYC Verification.
  - **Sprint 2:** Trip Engine, Category Filtering, Seat Reservation logic, Bookmarks.
  - **Sprint 3:** Discussion Boards (Threaded Comments) & Private 1-on-1 Chat Engine.
  - **Sprint 4:** Regression QA with Postman API Automation, Performance & Security Hardening.
- **Artifacts:** User Stories, Sprint Backlog, Burndown Metrics, and Postman API Documentation.

> 🗣️ **Speaker Notes:**
> *"From a software process perspective, we selected the Agile Scrum methodology. Rather than a rigid waterfall approach, Agile enables continuous integration, user story prioritization, and sprint-by-sprint automated API testing."*

---

## Slide 5: Requirements Engineering (SRS Summary)
### Functional Requirements (FR):
1. **User Management:** Registration with NID, Bcrypt hashed login, OTP password reset.
2. **Trip Operations:** Create, update, filter by category, and browse trips.
3. **Application Lifecycle:** Apply to join, host accept/reject, seat availability calculation.
4. **Communication:** Public nested replies and private 1-on-1 chat with read receipts.

### Non-Functional Requirements (NFR):
- **Security:** Stateless JWT authentication, SQL injection prevention via PDO prepared statements.
- **Performance:** Sub-200ms API response latency.
- **Data Integrity:** Strict foreign key cascading constraints (`ON DELETE CASCADE`) to eliminate dangling data.

> 🗣️ **Speaker Notes:**
> *"Our Requirements Engineering establishes clear functional boundaries, including KYC onboarding and seat allocation rules. On the non-functional side, our top priorities are data integrity, security, and sub-second response times across all endpoints."*

---

## Slide 6: 3-Tier System Architecture
- **Presentation Layer:** Client-side SPA / Mobile Web UI interacting via asynchronous RESTful HTTP calls.
- **Application Logic Layer:**
  - Centralized Route Dispatcher (`index.php`)
  - Middleware: JWT Token Validator & CORS Interceptor
  - Domain Controllers: Auth, Trip Engine, Chat, Comments, Bookmarks
  - External Service: PHPMailer SMTP Engine for OTP dispatch
- **Data Persistence Layer:**
  - Relational Database: MySQL (`travel_buddy`) with normalized tables.
  - Local File Storage: Encrypted file paths for user avatars, NID proofs, and trip covers.

> 🗣️ **Speaker Notes:**
> *"The system implements a classic 3-Tier Architecture. The client communicates with our PHP backend through stateless REST APIs. Requests pass through our central dispatcher and JWT middleware before reaching specialized business controllers, ensuring clean separation of concerns."*

---

## Slide 7: Data Flow Diagrams (DFD Level 0 & Level 1)
- **Level 0 (Context):**
  - Shows external actors: **Traveler**, **Trip Host**, and **SMTP Mail Service**.
  - Illustrates system boundary and high-level input/output data flows.
- **Level 1 (Decomposition):**
  - Decomposes into 5 core processes:
    1. *Auth & KYC (D1, D7)*
    2. *Trip Catalog (D2)*
    3. *Booking & Requests (D3)*
    4. *Communication & Chat (D4, D5)*
    5. *Watchlist & Bookmarks (D6)*

> 🗣️ **Speaker Notes:**
> *"Our Level 0 Context Diagram establishes system boundaries with Travelers, Hosts, and external email providers. In the Level 1 DFD, we decompose the system into five modular processes, mapping exactly how data flows into our persistent datastores."*

---

## Slide 8: Use Case Model & User Roles
- **Identified Actors:**
  - **Unauthenticated Guest:** Browse trips, register account, initiate OTP reset.
  - **Registered Traveler:** Submit join requests, bookmark trips, participate in public discussions, message hosts.
  - **Trip Host:** Create trips, manage seat quotas, evaluate applicant profiles, accept/reject join requests.
- **Key Relationships:**
  - `<<include>>` JWT Authentication on all transactional operations.
  - State dependency: Booking confirmation triggers automatic seat decrement.

> 🗣️ **Speaker Notes:**
> *"Here is our UML Use Case model. We categorized actors into Guests, Registered Travelers, and Trip Hosts. Note how critical actions such as requesting a trip or creating an itinerary include the token authentication use case."*

---

## Slide 9: Relational Database Architecture (ERD)
- **Key Entities:**
  - `user_info` & `user_login`: Separated for security normalization.
  - `trip_info`: Central repository for trip schedules, budget, and capacity.
  - `trip_request` & `confirm_trip`: Manages the application-to-confirmation pipeline.
  - `trip_comment`: Self-referential hierarchy (`parent_id`) supporting nested comments.
  - `chat_room` & `message`: Tracks 1-on-1 dialogue with message seen states.
- **Integrity Constraints:**
  - Foreign keys with `ON DELETE CASCADE` prevent orphaned records.
  - Compound unique keys prevent duplicate applications or multiple chat rooms for the same user-trip pair.

> 🗣️ **Speaker Notes:**
> *"Our database is normalized to 3NF. To maximize security, credentials in `user_login` are isolated from profile data in `user_info`. We also implemented self-referencing foreign keys on `trip_comment` to support nested discussions, and compound unique constraints to eliminate duplicate requests."*

---

## Slide 10: Dynamic Behavioral Modeling (Sequence Diagram)
- **Step 1:** Traveler queries and applies for an open trip with token verification.
- **Step 2:** System checks remaining capacity (`available_seats > 0`) and logs a `pending` request.
- **Step 3:** Host reviews incoming applications and verifies applicant credentials.
- **Step 4 (Atomic Transaction):**
  - Update `trip_request.status = 'accepted'`
  - Insert into `confirm_trip`
  - Decrement `trip_info.available_seats`
  - Spawn dedicated `chat_room` for pre-trip coordination.

> 🗣️ **Speaker Notes:**
> *"This sequence diagram details our most critical business flow: Trip Application and Seat Allocation. When the host accepts a traveler, an atomic database transaction updates the request status, reduces available seats, registers the traveler, and automatically initializes a private chat room."*

---

## Slide 11: Testing & Quality Assurance (SQA)
- **Automated API Testing (Postman Test Collection):**
  - Includes full test scripts asserting HTTP status codes (200, 201, 400, 401, 404).
  - Environment variables automatically extract and persist bearer tokens across test steps.
  - Validates multipart form-data (file upload validation) and JSON payloads.
- **Security Hardening:**
  - 100% PDO Prepared Statements preventing SQL Injection.
  - Bcrypt password encryption.
  - Expirable 6-digit OTP verification.

> 🗣️ **Speaker Notes:**
> *"To ensure software quality, we developed an automated Postman test suite that runs regression checks across all routes. The collection auto-captures JWT tokens and asserts HTTP response codes. Security is reinforced through parameterized PDO queries and Bcrypt hashing."*

---

## Slide 12: Future Roadmap & Conclusion
- **Future Enhancements:**
  - **Payment Escrow System:** Integration with mobile banking (bKash/Nagad) for security deposits.
  - **Real-Time WebSockets:** Replacing polling with bidirectional WebSockets for instant messaging.
  - **AI Recommendation Engine:** Pairing travelers based on travel interests and budget similarities.
  - **Trust & Rating Matrix:** Mutual 5-star reviews post-trip completion.
- **Conclusion:**
  - Travel Buddy provides a structured, secure, and complete solution to collaborative travel.
- **Thank You! Any Questions?**

> 🗣️ **Speaker Notes:**
> *"In future iterations, we plan to incorporate an escrow payment gateway and an AI-driven companion matching engine. In conclusion, Travel Buddy demonstrates rigorous Software Engineering principles—from requirement gathering and architectural modeling to automated testing. Thank you for your time, and we now welcome any questions."*
