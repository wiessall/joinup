## PhpStorm Configuration

1. **Open your project** in PhpStorm.

2. **Configure DDEV as a Docker-based interpreter**:
  - Go to `File` > `Settings` (or `Preferences` on macOS) > `Languages & Frameworks` > `Node.js and NPM`.
  - Click on the `...` button next to the `Node interpreter` field and select `Add`.
  - Choose `Docker` and configure it to use the DDEV container. Select the appropriate service from the list (usually, it will be something like `ddev-[your-project]-web`).
  - Set the `Package manager` field to `pnpm`.

3. **Add a new run configuration**:
  - Go to `Run` > `Edit Configurations`.
  - Click on the `+` button and select `npm`.
  - In the `Package` field, browse to your `package.json` file.
  - In the `Command` field, select `run`.
  - In the `Scripts` field, type `watch`.
  - Give the configuration a name, e.g., `DDEV pnpm watch`.

4. **Run the configuration**:
  - Select your newly created configuration from the run/debug configurations dropdown.
  - Click on the `Run` button to start the `watch` script.

## Visual Studio Code Configuration

1. **Open your project** in VS Code.

2. **Configure a Docker-based development environment**:
  - Install the [Dev Containers](https://marketplace.visualstudio.com/items?itemName=ms-vscode-remote.remote-containers) extension if you haven't already.
  - Add a `.devcontainer/devcontainer.json` file in your project with the following configuration (adjust the service name as necessary):

    ```json
    {
      "name": "DDEV Container",
      "context": "..",
      "dockerFile": "../.ddev/Dockerfile",
      "workspaceFolder": "/var/www/html",
      "postCreateCommand": "pnpm install --frozen-lockfile",
      "remoteUser": "vscode",
      "customizations": {
        "vscode": {
          "extensions": [
            "dbaeumer.vscode-eslint",
            "esbenp.prettier-vscode"
          ],
          "settings": {}
        }
      }
    }
    ```

3. **Create a new task to run `ddev exec pnpm run watch`**:
  - In your project, create a `.vscode/tasks.json` file with the following content:

    ```json
    {
      "version": "2.0.0",
      "tasks": [
        {
          "label": "DDEV pnpm watch",
          "type": "shell",
          "command": "ddev exec pnpm run watch",
          "group": {
            "kind": "build",
            "isDefault": true
          },
          "problemMatcher": [],
          "detail": "Run pnpm watch script in DDEV"
        }
      ]
    }
    ```

4. **Run the task**:
  - Press `Ctrl+Shift+P` (or `Cmd+Shift+P` on macOS) to open the Command Palette.
  - Type `Run Task` and select it.
  - Choose `DDEV pnpm watch` from the list of tasks.

These configurations will allow you to run `ddev exec pnpm run watch` easily within PhpStorm and VS Code, leveraging the capabilities of DDEV to manage your development environment.
