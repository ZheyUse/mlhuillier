❯ perfect, then I want you to add a new command called ml add -db <database>

add a new file called ml-add-db.php in /script folder

so the purpose for this is to add new schema/Database using cli instead of doin it manually in the workbench

when user run ml add -db sample_db
CLI will return a message
Do you want to add Table? (Y/N):
if Y then 
Add Table for <database> separated by comma:
ex. food, people, cars

else if no then just create the the <database> without the table.

if Y:
<database> has been created with table:
1. <table>
2. <table>
3. <table>

if N:
<database> has been created
