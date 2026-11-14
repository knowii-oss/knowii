# Community export

Use this runbook when the owner of a community asks for an export of its data. The DPA promises it (section 9, GDPR art. 28(3)(g)). The owner writes to contact@developassion.be. A self-service export for owners does not exist yet.

The export is a copy of personal data: names, email addresses, posts, files. Handle it with care.

## Steps

1. Check that the request comes from the email address of the owner's account. Open the community in the database or the admin tools and compare the address with the owner's email. If the address differs, do not export. Ask the owner to write again from the account address.
2. Find the slug of the community (the last part of its address, for example `gardeners`).
3. Run the command on the server, in the application container:

   ```bash
   php artisan communities:export gardeners
   ```

   The command prints the absolute path of one zip file, under `exports/` of the `local` disk: `community-<slug>-<YYYYMMDD-HHMMSS>.zip`.

4. Do not add `--with-direct-messages` by default. Direct messages are private between their participants. Use the option only when the owner asks for them in the request and you decided that the request is valid (for example, a legal duty of the owner). Then run `php artisan communities:export gardeners --with-direct-messages`.
5. Open the zip file and read `README.txt`. Check that the files you expect are there. Do not read the content for other reasons.
6. Send the file to the owner through a link that expires (for example a file sharing link that ends after a few days, with a password sent in another message). Never attach the file to an email.
7. Tell the owner that the file contains the email addresses of the members, and that the owner is responsible for it from then on.
8. The file stays on the server for `RETENTION_EXPORTS_DAYS` days (7 by default). Then `retention:prune` deletes it. If the owner confirms the download earlier, delete the file yourself.

## Check

1. `php artisan retention:prune --dry-run` shows the number of export files that are due for deletion in the `exports` line.
2. The log has one line `Community exported` with the community identifier. It never holds the content.
