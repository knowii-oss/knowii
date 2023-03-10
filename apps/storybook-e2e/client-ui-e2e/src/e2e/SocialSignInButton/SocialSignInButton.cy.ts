describe('client-ui: SocialSignInButton component', () => {
  beforeEach(() => cy.visit('/iframe.html?id=socialsigninbutton--primary'));

  it('should render the component', () => {
    cy.get('h1').should('contain', 'Welcome to SocialSignInButton!');
  });
});
