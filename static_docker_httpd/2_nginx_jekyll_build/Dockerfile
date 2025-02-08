FROM jekyll/minimal AS build
COPY . .
RUN jekyll b

FROM nginx:alpine
COPY --from=build /srv/jekyll/_site /usr/share/nginx/html
