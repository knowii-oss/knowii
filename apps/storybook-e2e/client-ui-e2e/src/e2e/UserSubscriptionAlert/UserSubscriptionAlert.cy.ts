describe('client-ui: UserSubscriptionAlert component', () => {
  beforeEach(() => cy.visit('/iframe.html?id=usersubscriptionalert--primary'));

  it('should render the component', () => {
    cy.get('h1').should('contain', 'Welcome to UserSubscriptionAlert!');
  });
});
