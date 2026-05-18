---


# Perfex CRM: Shipment Tracker & KYC Module

[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](https://opensource.org/licenses/MIT)
![Perfex Version](https://img.shields.io/badge/Perfex%20CRM-3.0.x%20%7C%203.1.x-green)
![Status](https://img.shields.io/badge/status-production%20ready-brightgreen)

> 🚀 100% open source, production ready modules for Perfex CRM. **No ionCube**, no hidden callbacks, no paid “pro” upsell, no telemetry.

---

## ✨ Overview
This repository provides **two standalone, drop-in modules** for **Perfex CRM**:

- **Shipment Tracker** — end-to-end shipment/tracking management for staff and customers.  
- **KYC Verification** — configurable customer verification workflow with document uploads, approvals, and optional access restrictions.

Both modules are built for real-world production use, designed to be lightweight, secure, and easy to maintain. They do **not** modify core Perfex files and can be installed/uninstalled cleanly.

> ⚠️ This is an **unofficial community project** and is **not affiliated with or endorsed by Perfex CRM**.

---

## 📦 Features

### 🚚 Shipment Tracker Module
| Feature | Details |
|---|---|
| 🧾 **Customer Tracking Page** | Branded tracking page inside the Perfex client portal (no external redirect required) |
| 🌍 **Courier Auto-Tracking** | Automatic status updates for **90+ couriers worldwide** (where supported by the courier API) |
| ✍️ **Manual Tracking** | Add/override tracking numbers and statuses for any custom/local courier |
| 📧 **Notifications** | Email (and optional SMS) notifications on status changes |
| 🧩 **Widget** | Public embeddable tracking widget for external websites |
| 🧠 **Staff Tools** | Manage shipments directly from invoices/orders; bulk import/update support |
| 🎨 **Theme Compatible** | Works with default Perfex themes and most custom themes |

### 🪪 KYC Verification Module
| Feature | Details |
|---|---|
| 🧱 **Tiered Verification** | Create unlimited verification tiers (e.g., Basic, Enhanced, Corporate) |
| 📄 **Document Requirements** | Define required documents per tier (ID, proof of address, business docs, etc.) |
| ☁️ **Client Uploads** | Customers upload documents securely from the client portal |
| ✅ **Approval Workflow** | Staff review, approve, reject, request re-upload with private notes |
| 🔒 **Access Control (Optional)** | Block unverified customers from accessing selected portal areas/features |
| ⏰ **Expiry Reminders** | Automatic reminders for expiring documents (configurable) |
| 🧾 **Audit Log** | Immutable audit trail of all verification actions |
| 🏠 **Self-Hosted** | All files/data stored on **your server** (no third-party KYC vendor lock-in required) |

---

## 🖼️ Screenshots

> 📌 Replace the placeholder images below with your own screenshots (drag & drop into GitHub issues or add to `/docs/screenshots/` and link them).

| Area | Preview |
|---|---|
| **Client Portal — Shipment Tracking** | ![Client Tracking](docs/screenshots/client-tracking.png) |
| **Staff — Manage Shipments** | ![Staff Shipments](docs/screenshots/staff-shipments.png) |
| **Client Portal — KYC Upload** | ![KYC Upload](docs/screenshots/kyc-upload.png) |
| **Staff — KYC Review** | ![KYC Review](docs/screenshots/kyc-review.png) |

---

## ✅ Requirements
- **Perfex CRM**: `3.0.x` or `3.1.x` (latest recommended)  
- **PHP**: `7.4+` (or `8.0+` depending on your Perfex version)  
- **MySQL/MariaDB**: `5.7+` / `10.x+`  
- **Permissions**: Ability to upload modules via Setup → Modules  
- **Cron/Task Scheduler** (recommended): Required for automatic shipment status updates & KYC reminders  

---

## 🚀 Installation
1. **Download** the latest release from the [Releases](../../releases) page (or clone this repo and zip the module folders).  
2. In Perfex CRM, go to **Setup → Modules**.  
3. Click **Upload Module** and select the `.zip` file(s).  
4. **Activate** the module(s).  
5. Configure settings under the new menu items (e.g., **Shipments**, **KYC Verification**).  

No core file edits are made. Uninstalling removes module data tables/settings as defined in the module’s uninstall routine.

---

## 🔧 Configuration (Quick Start)

### Shipment Tracker
- Set default courier provider (if using auto-tracking) and API credentials (if required).  
- Configure notification templates (email/SMS).  
- Enable the client portal menu item for tracking (enabled by default).  
- (Optional) Add the **public tracking widget** embed code to your external website.

### KYC Verification
- Create one or more **verification tiers**.  
- Add **required document types** per tier.  
- Choose whether to **restrict portal access** for unverified customers.  
- Configure reminder schedule for expiring documents.  

---

## 🔐 Security & Privacy Notes
- All uploaded KYC documents are stored on **your own server** (filesystem or configured storage).  
- Access to KYC review is controlled by Perfex staff roles/permissions (module adds permission keys).  
- No external analytics, no remote calls home, and no obfuscated code.  
- Always keep Perfex, PHP, and this module updated to the latest versions.

---

## 🧭 FAQ

**Q: Does this work with the latest Perfex CRM version?**  
A: Yes. The modules target **3.0.x and 3.1.x**. If Perfex releases a breaking change, we’ll publish a patched release promptly.

**Q: Is ionCube or any paid license required?**  
A: **No.** 100% free and open source under MIT.

**Q: Can I use this on multiple clients/projects?**  
A: Yes, MIT license allows commercial and personal use.

**Q: Which couriers are supported for auto-tracking?**  
A: 90+ couriers are supported depending on the tracking integration configured in the module. Some couriers may require API keys or may have regional restrictions.

**Q: Will KYC documents be sent to a third party?**  
A: No. Documents remain on your server unless you explicitly integrate a third-party verification provider (the module does not include one by default).

**Q: Can I block unverified customers from placing orders / accessing invoices?**  
A: Yes. The KYC module includes optional access restrictions you can configure per verification tier/area.

**Q: How do I get updates?**  
A: Watch/star this repo and check the [Releases](../../releases) page. You can also pull changes and re-upload the module (backup recommended).

---

## 🛠️ Troubleshooting

**Module doesn’t appear after upload**  
- Ensure you uploaded the correct `.zip` (the module folder root should be present).  
- Check file permissions on `application/modules/` and `uploads/`.  
- Clear Perfex cache: **Setup → Utilities → Clear Cache** (if available).

**Shipment status not updating automatically**  
- Confirm your **cron/task scheduler** is configured and running for Perfex.  
- Check module settings for the courier API credentials (if applicable).  
- Review server logs for API errors/timeouts.

**Customers can’t see the Tracking / KYC pages**  
- Verify the menu items are enabled for the client portal.  
- Ensure the customer role has the required permissions (module permission keys).  
- Re-login as the customer to refresh session/menus.

**File uploads fail (KYC)**  
- Check PHP upload limits: `upload_max_filesize`, `post_max_size`, `max_execution_time`.  
- Ensure the uploads directory is writable by the web server.  
- Validate allowed file types/mime settings in module configuration.

**Emails not sending on status change**  
- Confirm Perfex email settings (SMTP) are working.  
- Check notification templates are enabled in the module settings.  
- Look at server mail logs / Perfex activity log for errors.

If you’re stuck, please open an [Issue](../../issues) with: Perfex version, PHP version, module version, and steps to reproduce (screenshots/logs are very helpful).

---

## 🤝 Contributing
Pull requests are welcome! Please open an issue first to discuss major changes.  
- Follow existing code style and Perfex module conventions.  
- Include migration scripts if database changes are required.  
- Add/update documentation and screenshots where relevant.

---

## 📄 License
This project is licensed under the **MIT License** — see the [LICENSE](LICENSE) file for details.

---

## ⭐ Support the Project
If you find this module useful, please **star** the repo and share it with the Perfex community. It helps others discover free, open-source alternatives to paid/obfuscated modules.

---

Need me to also generate a **CHANGELOG.md**, **CONTRIBUTING.md**, or a ready-to-use **module zip folder structure** guide for developers? Just tell me. ```
