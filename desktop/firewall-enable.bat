@echo off
rem One-time allow rule for Macaron LAN tablet access. Requires elevation; fails silently to non-blocking.
netsh advfirewall firewall delete rule name="Macaron-LAN" >nul 2>&1
netsh advfirewall firewall add rule name="Macaron-LAN" dir=in action=allow protocol=TCP localport=%1 enable=yes >nul 2>&1
