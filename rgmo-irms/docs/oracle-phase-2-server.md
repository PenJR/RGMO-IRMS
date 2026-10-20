# Phase 2: Oracle account and server

Prepared October 18, 2026 (Asia/Manila). No cloud resource has been provisioned.
Oracle signup/console access is required to complete this phase.

## Account

1. Visit https://www.oracle.com/cloud/free/ and start Free Tier signup.
2. Complete email, phone, and payment-card verification directly with Oracle.
   Never put card details, passwords, or private keys in chat or the repository.
3. Choose a nearby home region (Singapore if offered and appropriate). Always Free
   compute must be created in the home region. Regional capacity is not guaranteed;
   check the region choice carefully before completing signup.
4. Stay on the Free Tier. Trial credits are temporary; use resources explicitly
   marked Always Free eligible for this deployment.

## Instance settings

Open Compute > Instances > Create instance. Console labels may vary.

| Setting | Intended value |
| --- | --- |
| Name | rgmo-irms |
| Image | Canonical Ubuntu 24.04 LTS, compatible with the selected shape |
| Shape | VM.Standard.A1.Flex, Always Free eligible |
| CPU / RAM | 1 OCPU / 6 GB initially |
| Boot volume | 50 GB, default performance; confirm it fits the free allowance |
| Network | VCN with public subnet, internet gateway and internet route |
| Public IPv4 | Assign a public address for SSH and later website access |
| SSH | Generate a key pair and download both keys, or upload an existing public key |

Oracle currently documents an Always Free A1 total of 2 OCPUs / 12 GB and 200 GB
combined boot/block volume storage. Existing resources consume those allowances.
Review the actual account allowance and console cost/eligibility before creating.
Ubuntu A1 must be ARM64; don't force an x86 image onto an ARM shape.
Avoid paid add-ons such as a load balancer or NAT gateway for this small deployment.

If A1 reports out of capacity, try another availability domain within the home
region if offered, or retry later. Do not switch to an arbitrary paid shape. An
Always Free E2.1.Micro is a possible fallback, but its 1 GB RAM needs a revised
server/build plan rather than assuming the A1 settings apply.

## SSH and storage verification

Restrict TCP 22 ingress to your current public IP /32 in the VCN security list or
network security group. Keep the private key on your laptop with permission 600.
Do not paste it into chat. Never open PHP-FPM port 9000 to the internet.

Example (replace the placeholders with your downloaded key and instance address):

```bash
chmod 600 /path/to/your-private-key
ssh -i /path/to/your-private-key ubuntu@YOUR_SERVER_PUBLIC_IP
```

Confirm the SSH host-key fingerprint through an independently trusted Oracle
console channel before accepting it. Once connected, these are read-only checks:

```bash
cat /etc/os-release
uname -m
free -h
df -h /
lsblk -f
```

Expected: Ubuntu 24.04, aarch64 on A1, roughly 6 GB RAM, and an attached boot disk.
Keep that boot volume when replacing the VM; deleting a volume deletes its data.
Persistent disks still need independent backups. Oracle may reclaim idle Free Tier
instances, so persistence is not a substitute for backup retention.

The persistent boot volume can hold shared database/upload directories in Phase 3.
There is no need to provision a second database service or extra disk in Phase 2.
Later, allow HTTP/HTTPS (80/443) in both OCI networking and the host firewall when
configuring Nginx and HTTPS. Do not change the Render deployment yet.

## Phase 2 completion evidence

- Instance state RUNNING with Always Free eligibility verified.
- Record the region, selected shape, CPU/RAM, and public IP.
- SSH succeeds and the read-only commands above match the intended configuration.
- Keys are stored privately and the boot volume is present.

Until that evidence exists, Phase 2 is prepared but not complete. Installing packages
and deploying Laravel belong to Phase 3 and Phase 4 respectively.

Sources:
- https://www.oracle.com/cloud/free/faq/
- https://docs.oracle.com/en-us/iaas/Content/FreeTier/freetier_topic-Always_Free_Resources.htm
- https://docs.oracle.com/en-us/iaas/Content/Compute/Tasks/launchinginstance.htm
