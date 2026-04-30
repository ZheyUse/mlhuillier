1. Add centralized db command: ml migrate global
-> When User decentralized userdb from ml migrate -db <database>, they can also centralized it again using ml migrate global
-> CLI Return: Project: <Projectname> userdb has been centralized.

2. Add a command to add menu and submenu instantly while in the root path: ml add menu
-> CLI Return: Enter menu to be added in sidebar: ex. Profile
-> CLI Return: Enter Submenu(s) for <Menu>: ex. Settings, Extentions, Passwords, Details
-> CLI Return: 
                <Menu> has been Created
                    -> <submenu> has been added
                    -> <submenu> has been added
                    -> <submenu> has been added
                    -> <submenu> has been added

-> CLI Return: Do you want me to create the necessary template of the created <Menu> and <Submenu>? (Y/N): ex. Y

-> CLI Return: Creating src/pages/<Menu> ... OK
                Creating src/pages/<Menu>/<submenu>/<submenu>.php ... OK
                Creating src/pages/<Menu>/<submenu>/<submenu>.css ... OK

Purpose: It creates a folder in the /src/pages/<Menu> and its necessary <submenu>.php and css in /src/pages /<Menu>/<Submenu> folder with an already made includes of header_ui.php and sidebar.php and other necessary guards.

3. Add a command for submenu instantly for an existing Menu while in the root path: ml add submenu
-> CLI Return: List of Menu:
            -> 1. <menu>
            -> 2. <menu>
            -> 3. <menu>
-> Select Menu in the list above using numbers: ex. 3
-> CLI Return: Enter Submenu(s) for <Menu>: <submenu>, <submenu>, <submenu>, <submenu>
-> CLI Return: 
                <Menu> has been updated
                    -> <submenu> has been added
                    -> <submenu> has been added
                    -> <submenu> has been added
                    -> <submenu> has been added

-> CLI Return: Do you want me to create the necessary template of the created <Submenu>  of <Menu>? (Y/N): ex. Y

-> CLI Return: Creating src/pages/<Menu>/<submenu>/<submenu>.php ... OK
                Creating src/pages/<Menu>/<submenu>/<submenu>.css ... OK
                

4. Create a command to show / list all Menu and Submenu of the project: ml show sidebar
-> CLI Return: List of Menu and Submenu of <projectname>:
            -> <Menu>
                -> <Submenu>
                -> <Submenu>
                -> <Submenu>
            -> <Menu>
                -> <Submenu>
                -> <Submenu>
            -> <Menu>
                -> <Submenu>
                -> <Submenu>
                -> <Submenu>
                -> <Submenu>
                -> <Submenu>
                -> <Submenu>

