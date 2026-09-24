import type { NextConfig } from "next";
import createNextIntlPlugin from "next-intl/plugin";

const withNextIntl = createNextIntlPlugin("./src/i18n/request.ts");

const nextConfig: NextConfig = {
  async redirects() {
    return [
      {
        source: "/epurchase",
        destination: "/epurchase/items",
        permanent: false,
      },
    ];
  },
};

export default withNextIntl(nextConfig);
