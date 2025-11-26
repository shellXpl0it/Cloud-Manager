# Cloud Manager

## Your Personal, Open-Source, Self-Hosted Cloud Solution

Cloud Manager is an open-source, self-hosted cloud storage solution designed to give you complete control over your files. Easily store, manage, and access your documents, media, and other files from anywhere, all hosted on your own infrastructure.

Take back ownership of your data with a simple, efficient, and customizable cloud experience.

## ✨ Features

*   **Self-Hosted Control:** Maintain full privacy and control over your data by hosting it on your own server.
*   **Broad File Support:** Seamlessly manage a wide array of file types, from documents to multimedia.
*   **Simple Setup:** Get your personal cloud up and running with straightforward installation steps.
*   **Easy File Management:** Upload, download, and organize your files through a user-friendly interface.

## 📂 Supported File Types

Cloud Manager is designed to handle a diverse range of file formats, including:

*   **Documents:** `txt`, `pdf`, `docx`, `pptx`
*   **Images:** `png`, `jpg`, `jpeg`
*   **Audio:** `mp3`, `wav`
*   **Video:** `mp4`, `mov`

## 🚀 Getting Started

Follow these steps to set up your own Cloud Manager instance.

### Prerequisites

Before you begin, ensure you have the following installed on your server:

*   A web server (e.g., Apache, Nginx)
*   PHP (version 7.4 or higher recommended)

### Installation

1.  **Download or Clone:** Obtain the Cloud Manager source code. You can clone the repository using Git:
    ```bash
    git clone https://github.com/shellxpl0it/cloud-manager
    ```
    Or download the ZIP archive and extract it.

2.  **Place Files:** Move the extracted PHP files into your web server's document root (e.g., `htdocs` for Apache, `www` for Nginx).

3.  **Configure Web Server:** Ensure your web server is configured to serve PHP applications from the directory where you placed the files.

4.  **Setup Save Paths:** Locate the configuration file (e.g., `config.php` or similar, you might need to create one if it doesn't exist) and define the absolute path where you want your files to be stored. Ensure this directory has appropriate write permissions for your web server user.
    ```php
    define('UPLOAD_DIR', '/path/to/your/cloud/storage');
    ```

### Usage

Once installed and configured, navigate to the application's URL in your web browser (e.g., `http://localhost/` or `http://127.0.0.1/`). You can then upload, download, and manage your files through the web interface.

## 📸 Screenshots

Here's a glimpse of Cloud Manager in action:

![Screenshot of Cloud Manager](https://cdn.discordapp.com/attachments/1439272416093143061/1443273267719700590/image.png?ex=69287879&is=692726f9&hm=31e8a54b54599bb4f627562f71a71c0bd8474973045f5d2e24b8b0e37a39e7d9&)

## 🤝 Credits

**Author:** shellxpl0it

## 📄 License

This project is available under a Proprietary License. See the `LICENSE` file for more details.

## 💡 Contributing

Contributions are welcome! If you'd like to contribute, please refer to `CONTRIBUTING.md` for guidelines on how to submit pull requests, report bugs, and suggest features.

## 📧 Contact

For questions, support, or feedback, please open an issue on the GitHub repository.
