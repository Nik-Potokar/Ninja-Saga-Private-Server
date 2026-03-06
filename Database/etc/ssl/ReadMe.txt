Cannot access directory or SSL certificate not found

The clan and crew vhost configs use Laragon's shared SSL certificate. If you see certificate-related errors, verify the SSL lines in both files read:

Into your Laragon folder drop:

clan.ninjasage.id.pem
clan.ninjasage.id-key.pem
crew.ninjasage.id.pem
crew.ninjasage.id-key.pem
