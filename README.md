<div align="center">
  <img src="https://raw.githubusercontent.com/WasiCo/sdk-php/master/wasi-logo.png" width="200" alt="Wasi API Sync">
  <h1>Wasi Sync PRO by Lytta</h1>
  <p><strong>El conector API definitivo para agencias inmobiliarias. Sincroniza Wasi con WordPress usando Directorist o Advanced Custom Fields (ACF).</strong></p>
  
  <p>
    <a href="https://www.lytta.it/" target="_blank">
        <img src="https://img.shields.io/badge/Developed_by-Lytta-00a0d2?style=for-the-badge&logo=wordpress" alt="Lytta">
    </a>
    <img src="https://img.shields.io/badge/Version-12.0.0-success?style=for-the-badge" alt="Version 12.0.0">
    <img src="https://img.shields.io/badge/License-Freemium-ff69b4?style=for-the-badge" alt="Freemium License">
  </p>
</div>

---

## 🚀 Overview

**Wasi Sync PRO** is an advanced WordPress plugin that automatically synchronizes real estate properties from the Wasi CRM directly into your WordPress website. 
Instead of being locked into a single theme, this plugin acts as a universal bridge using an **Adapter Architecture**, allowing you to seamlessly integrate property data into popular directories like **Directorist** or custom-built solutions using **Advanced Custom Fields (ACF)**.

**[🌐 Español / Spanish Available]** 
*This plugin is fully translated and supports i18n out of the box for LATAM and Spanish markets (`es_ES` and `it_IT` included).*

---

## ✨ Core Features

*   **⚡ Universal Adapters:** Choose where the data goes inside WordPress. Use the out-of-the-box **Directorist** mapping, or push raw property data into standard **ACF fields** to build your own custom theme (Houzez, WP Residence, Listify).
*   **🛠 Automated Cron Sync:** Set rules to fetch new properties hourly, daily, or weekly without lifting a finger.
*   **🧹 Orphan Cleanup Engine:** Automatically detects when a property is deleted or unpublished on Wasi and marks it as Draft in WordPress to prevent 404s.
*   **📥 Smart Taxonomy Mapping:** Map specific Wasi Category IDs (e.g. `2=Apartamento, 14=Casa`) directly to your WordPress taxonomy terms.
*   **📧 Email Reporting:** Get a detailed sync report sent to your admin email after every automated execution.
*   **🔄 Integrated GitHub Auto-Updater:** The plugin self-updates directly from GitHub Releases to patch security issues instantly.

---

## 💎 Freemium vs PRO License

The plugin operates on a commercial **Freemium** model. Out of the box, it provides a stable environment to test the API integration.

### Free Version (0.00€)
*   **Limit:** Hard-capped to **10 Properties maximum**. Once the limit is reached, the sync stops.
*   **Images:** Only downloads the primary Cover Image to save server storage.
*   **Support:** Community GitHub support.

### PRO Version (Premium)
*   Unlock **UNLIMITED** property synchronization.
*   Downloads **Full Image Galleries** for every property.
*   Enables intensive high-frequency Hourly cron jobs.
*   Priority support from Lytta.

> 🛒 **Purchase a PRO License (120€/year incl. VAT or 10€/month)**
> Email us at [info@lytta.it](mailto:info@lytta.it) to request an invoice and receive your UNLIMITED activation key.

---

## 📦 Installation

1. Copy the URL of this GitHub repository: `https://github.com/lytta-it/wasi-sync-pro/archive/refs/heads/main.zip`
2. Download the `.zip` file.
3. In your WordPress Admin Dashboard, navigate to **Plugins > Add New > Upload Plugin**.
4. Select the downloaded `.zip` file and click **Install Now**.
5. Click **Activate Plugin**.
6. The plugin will appear in the sidebar as **Wasi Sync**.

## ⚙️ Configuration

1. Obtain your **Company ID** and **Token** from your Wasi CRM dashboard (API Settings).
2. Go to **Wasi Sync > Base API Settings** in WordPress.
3. Choose your Data Destination (Directorist vs ACF).
4. Enter your Wasi credentials and Save.
5. *(Optional)* Enter your PRO License key to unlock unlimited migrations.

---

## 👨‍💻 Custom Development & Theme Integration

The Real Estate world is complex. If you are building a custom website or using massive premium themes like **Houzez, Goya, or WP Residence**, having raw ACF fields might not be enough. 

Lytta offers **Custom Adapter Development**. We can write a specific bridging class for your unique WordPress setup.
Contact us for a tailored quote at [info@lytta.it](mailto:info@lytta.it).

<br>

<div align="center">
  <p>Developed with ❤️ by <a href="https://www.lytta.it/">Lytta</a></p>
</div>
