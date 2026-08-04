#!/bin/bash
set -e

echo "Starting VPS provisioning..."

# ------------------------
# System update
# ------------------------

apt update
apt upgrade -y

apt install -y \
    curl \
    wget \
    git \
    unzip \
    htop \
    nano \
    vim \
    jq \
    ca-certificates \
    gnupg \
    ncdu \
    vnstat \
    ufw \
    fail2ban

systemctl enable fail2ban
systemctl start fail2ban

# ------------------------
# Timezone
# ------------------------

timedatectl set-timezone Asia/Tehran


# ------------------------
# Create swap (4GB)
# ------------------------

if [ ! -f /swapfile ]; then

    fallocate -l 4G /swapfile

    chmod 600 /swapfile

    mkswap /swapfile

    swapon /swapfile

    echo "/swapfile none swap sw 0 0" >> /etc/fstab

fi


# Improve swap behavior

echo "vm.swappiness=10" > /etc/sysctl.d/99-swappiness.conf

sysctl --system


# ------------------------
# Install Docker
# ------------------------

curl -fsSL https://get.docker.com | sh


systemctl enable docker
systemctl start docker


# Docker compose plugin

apt install -y docker-compose-plugin

# ------------------------
# Install vnstat
# ------------------------

# Detect primary network interface
INTERFACE=$(ip route show default | awk '/default/ {print $5}')

if [ -n "$INTERFACE" ]; then
    vnstat --add -i "$INTERFACE" || true
fi

systemctl enable vnstat
systemctl restart vnstat

# ------------------------
# Docker log limits
# ------------------------

mkdir -p /etc/docker

cat > /etc/docker/daemon.json <<EOF
{
  "log-driver": "json-file",
  "log-opts": {
    "max-size": "50m",
    "max-file": "5"
  }
}
EOF


systemctl restart docker


# ------------------------
# Finished
# ------------------------

echo "Provisioning complete"